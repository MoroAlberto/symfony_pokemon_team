<?php

namespace App\Service;

use App\Entity\Type;
use App\Repository\TypeRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TypeService
{
    public const CACHE_KEY = 'type_key';

    public function __construct(
        private TypeRepository $typeRepository,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @return Type[]
     */
    public function getTypeCached(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(3600);

            return $this->typeRepository->findBy([], ['name' => 'ASC']);
        });
    }

    /**
     * Must be called whenever types are added or removed.
     */
    public function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }
}
