<?php

declare(strict_types=1);

namespace Astral\Cache;

/**
 * Contrat de stockage élémentaire (drivers).
 *
 * Sémantique alignée sur Core\Cache 2.0 :
 * - has() est indépendant de la valeur (hit-null possible) ;
 * - get() retourne $default uniquement sur miss / invalidité / expiration ;
 * - TTL en secondes ≥ 1 ;
 * - clear() limité au store / préfixe courant (jamais FLUSHDB Redis partagé).
 */
interface CacheStoreInterface
{
    /**
     * Lit une entrée valide. Un hit peut retourner null.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Écrit une entrée avec TTL en secondes (≥ 1).
     *
     * @throws \InvalidArgumentException si $ttl < 1 ou valeur non autorisée
     * @throws \RuntimeException si l'écriture échoue (ex. FileStore)
     */
    public function set(string $key, mixed $value, int $ttl): void;

    /** Entrée existante, valide et non expirée (y compris value null). */
    public function has(string $key): bool;

    /** Suppression best-effort. */
    public function delete(string $key): void;

    /**
     * Purge limitée au store / namespace courant.
     *
     * @return int Nombre d'entrées effectivement purgées (best-effort)
     */
    public function clear(): int;
}
