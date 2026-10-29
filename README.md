# astral-cache

Cache **multi-drivers** opt-in pour Astral MVC — array, file, core, Redis, Memcache(d).

> Explicite, sans magie. Provider facultatif. Aucune dépendance Redis/Memcache dans `astral-core`.

## Compatibilité

- PHP `^8.3`
- `astral-php/astral-core` `^2.0`

## Installation

**Local (path) :**

```json
"repositories": [{ "type": "path", "url": "../astral-cache" }]
```

```bash
composer require astral-php/astral-cache:@dev
```

**Packagist** (après publication) : `composer require astral-php/astral-cache`.

Fusionner `stubs/.env.cache.example` dans le `.env` de l’app.

## Usage minimal (sans provider)

```php
use Astral\Cache\CacheRepository;
use Astral\Cache\Store\FileStore;

$cache = new CacheRepository(new FileStore(__DIR__ . '/../storage/cache', 'astral:'));

$users = $cache->remember('users.all', 3600, fn () => $dao->findAll());
$cache->forget('users.all');
```

## Service Provider (optionnel)

Dans `config/dependencies.php` :

```php
Astral\Cache\CacheServiceProvider::class,
```

Puis injection :

```php
use Astral\Cache\CacheRepository;

public function __construct(private CacheRepository $cache) {}
```

| Variable | Défaut | Rôle |
|----------|--------|------|
| `CACHE_DRIVER` | `file` | `file` \| `array` \| `core` \| `redis` \| `memcache` |
| `CACHE_PATH` | `storage/cache` | Répertoire file / core |
| `CACHE_PREFIX` | `astral:` | Préfixe de clés |
| `CACHE_TTL` | `3600` | TTL défaut (secondes) |
| `REDIS_*` | localhost | Uniquement si `CACHE_DRIVER=redis` |
| `MEMCACHE_*` | localhost:11211 | Uniquement si `memcache` |

## Sémantique (alignée Core\Cache 2.0)

| Règle | Comportement |
|-------|----------------|
| `null` | `has()` = true si entrée valide ; `get()` peut retourner `null` |
| TTL | Secondes `int` ≥ 1 ; sinon `InvalidArgumentException` |
| Valeurs | Scalaires / tableaux ; **pas d’objets** |
| `clear()` / `flush()` | Limité au store / préfixe (jamais FLUSHDB / flush Memcache serveur) |
| `remember()` | Best-effort (pas de lock stampede) |

## Drivers

| Driver | Extension | Notes |
|--------|-----------|--------|
| `array` | — | Processus courant |
| `file` | — | Défaut install simple |
| `core` | — | Adaptateur `Core\Cache` (migration) |
| `redis` | `ext-redis` | `clear()` = SCAN + DEL préfixe |
| `memcache` | `ext-memcache` ou `ext-memcached` | Laragon = souvent Memcache ; `clear()` = bump génération |

## Tests

```bash
composer install
composer test
```

Les suites Redis / Memcache se skipent si extension ou serveur absent.

## Liens

- Spec : `astral-informations/astral-cache/Roadmap/spec-astral-cache-2.0.md`
- Core Cache 2.0 : `astral-informations/astral-core/Roadmap/spec-cache-2.0.md`
