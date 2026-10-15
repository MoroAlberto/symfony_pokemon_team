<?php

namespace App\Service;

use App\Entity\Team;
use App\Exception\InvalidTeamDraftException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keeps in session the Pokémon drawn by the server while a team is being created,
 * so that the browser can only pick among them (by token) and cannot forge Pokémon data.
 *
 * Each create page has its own draft, so that pages open in several tabs don't interfere.
 * This class is the only place enforcing the rules on the Pokémon of a new team.
 *
 * @phpstan-import-type PokemonData from PokemonService
 * @phpstan-import-type DrawnPokemon from PokemonService
 */
class TeamDraft
{
    // How many create pages (e.g. tabs, or teams left half-done) can be in progress at the same time
    public const MAX_DRAFTS = 20;
    public const ALREADY_SAVED_MESSAGE = 'This team was already saved, start a new team to create another one.';
    private const SESSION_KEY = 'team_drafts';
    private const COMPLETED_SESSION_KEY = 'team_drafts_completed';

    public function __construct(private RequestStack $requestStack)
    {
    }

    /**
     * Starts a new, empty draft and returns its id.
     *
     * When there are too many drafts, empty ones are forgotten first (pages where nothing was drawn,
     * e.g. after Reset), then the least recently used ones.
     */
    public function start(): string
    {
        // The prefix keeps ids from looking numeric, PHP would turn them into integer array keys
        $draftId = 'd'.$this->randomId();
        $drafts = $this->getDrafts();
        while (\count($drafts) >= self::MAX_DRAFTS) {
            $empty = array_keys(array_filter($drafts, fn (array $draft) => [] === $draft));
            unset($drafts[$empty[0] ?? array_key_first($drafts)]);
        }
        $drafts[$draftId] = [];
        $this->saveDrafts($drafts);

        return $draftId;
    }

    public function exists(string $draftId): bool
    {
        return \array_key_exists($draftId, $this->getDrafts());
    }

    /**
     * @return array<string, PokemonData> Drawn Pokémon indexed by token
     */
    public function all(string $draftId): array
    {
        return $this->getDrafts()[$draftId] ?? [];
    }

    /**
     * Draws a Pokémon with $draw and stores it in the draft, unless the team is already full.
     *
     * @param callable(): PokemonData $draw
     *
     * @return DrawnPokemon The drawn Pokémon, with its token
     *
     * @throws InvalidTeamDraftException
     */
    public function add(string $draftId, callable $draw): array
    {
        $draft = $this->getDraft($draftId);
        if (\count($draft) >= Team::MAX_POKEMON) {
            throw new InvalidTeamDraftException(sprintf('A trainer cannot own more than %d Pokémon.', Team::MAX_POKEMON));
        }

        $pokemon = $draw();
        $token = 't'.$this->randomId();
        $this->saveDraft($draftId, $draft + [$token => $pokemon]);

        return ['token' => $token] + $pokemon;
    }

    /**
     * Forgets the drawn Pokémon that the page does not show, e.g. because their response was lost.
     *
     * @param array<mixed> $tokens Tokens of the Pokémon shown on the page, as sent by the browser
     *
     * @throws InvalidTeamDraftException
     */
    public function retain(string $draftId, array $tokens): void
    {
        $tokens = array_filter($tokens, 'is_string');
        $this->saveDraft($draftId, array_intersect_key($this->getDraft($draftId), array_flip($tokens)));
    }

    /**
     * Returns the Pokémon chosen for the team, after checking the choice is valid.
     *
     * @param mixed $tokens Tokens as submitted by the browser
     *
     * @return list<PokemonData> The chosen Pokémon, in the same order as the tokens
     *
     * @throws InvalidTeamDraftException
     */
    public function pick(string $draftId, mixed $tokens): array
    {
        $draft = $this->getDraft($draftId);

        if (!\is_array($tokens) || !array_is_list($tokens) || [] === $tokens) {
            throw new InvalidTeamDraftException('Please add at least one Pokémon to your team by pressing the "Gotta Catch \'Em All" button.');
        }
        if (\count($tokens) > Team::MAX_POKEMON) {
            throw new InvalidTeamDraftException(sprintf('A trainer cannot own more than %d Pokémon.', Team::MAX_POKEMON));
        }
        foreach ($tokens as $token) {
            if (!\is_string($token) || !\array_key_exists($token, $draft)) {
                throw new InvalidTeamDraftException('Your team contains an invalid Pokémon, please create it again.');
            }
        }
        if (\count(array_unique($tokens)) !== \count($tokens)) {
            throw new InvalidTeamDraftException('Your team contains the same Pokémon more than once.');
        }

        return array_map(fn (string $token) => $draft[$token], $tokens);
    }

    /**
     * Forgets the draft once its team is saved, but remembers it was saved, so that submitting
     * the same page again (e.g. a double click) can be recognized.
     */
    public function complete(string $draftId): void
    {
        $drafts = $this->getDrafts();
        unset($drafts[$draftId]);
        $this->saveDrafts($drafts);

        $session = $this->requestStack->getSession();
        $completed = [...$session->get(self::COMPLETED_SESSION_KEY, []), $draftId];
        $session->set(self::COMPLETED_SESSION_KEY, \array_slice($completed, -self::MAX_DRAFTS));
    }

    public function isCompleted(string $draftId): bool
    {
        return \in_array($draftId, $this->requestStack->getSession()->get(self::COMPLETED_SESSION_KEY, []), true);
    }

    /**
     * Random id, overridable by the tests.
     */
    protected function randomId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * @return array<string, PokemonData>
     *
     * @throws InvalidTeamDraftException
     */
    private function getDraft(string $draftId): array
    {
        if ($this->isCompleted($draftId)) {
            throw new InvalidTeamDraftException(self::ALREADY_SAVED_MESSAGE);
        }
        if (!$this->exists($draftId)) {
            throw new InvalidTeamDraftException('This team has expired, please create it again.');
        }

        return $this->all($draftId);
    }

    /**
     * @param array<string, PokemonData> $draft
     */
    private function saveDraft(string $draftId, array $draft): void
    {
        $drafts = $this->getDrafts();
        // Move the draft to the end, so the drafts stay sorted from least to most recently used
        unset($drafts[$draftId]);
        $drafts[$draftId] = $draft;
        $this->saveDrafts($drafts);
    }

    /**
     * @return array<string, array<string, PokemonData>> Drafts indexed by id
     */
    private function getDrafts(): array
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY, []);
    }

    /**
     * @param array<string, array<string, PokemonData>> $drafts
     */
    private function saveDrafts(array $drafts): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $drafts);
    }
}
