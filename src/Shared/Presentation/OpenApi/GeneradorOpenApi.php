<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\OpenApi;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionMethod;
use Symfony\Component\Yaml\Yaml;

/**
 * Arma el contrato OpenAPI 3 a partir de las rutas registradas y de las reglas
 * `validate([...])` de cada controlador. No hay nada que mantener a mano: si
 * cambia una ruta o una regla, `php artisan openapi:generar` actualiza el
 * archivo y una prueba falla si se olvida hacerlo.
 */
final class GeneradorOpenApi
{
    private const PREFIJO = 'api/v1/';

    /** Metodos que crean un recurso y por eso responden 201. */
    private const CREAN = ['crear', 'registrar', 'solicitar', 'ejecutar', 'emitirComprobante'];

    public function __construct(private readonly Router $router) {}

    public function yaml(): string
    {
        return Yaml::dump($this->documento(), 12, 2, Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
    }

    /** @return array<string, mixed> */
    public function documento(): array
    {
        $paths = [];
        $tags = [];

        foreach ($this->rutas() as $ruta) {
            [$clase, $metodo] = $this->accion($ruta);
            $paquete = $this->paquete($clase);
            $tags[$paquete] = ['name' => $paquete];

            $uri = '/'.substr($ruta->uri(), strlen(self::PREFIJO));
            $verbo = strtolower($ruta->methods()[0]);
            $paths[$uri][$verbo] = $this->operacion($ruta, $clase, $metodo, $paquete, $verbo);
        }
        ksort($paths);
        ksort($tags);

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'MS-03 Comercial y Financiero',
                'version' => '1.0.0',
                'description' => 'Pagos, reembolsos, promociones, acuerdos comerciales, comisiones y liquidaciones. '
                    .'Los importes viajan en centavos. Las respuestas exitosas vienen envueltas en `{data}`; '
                    .'los errores como `{codigo, mensaje}`.',
            ],
            'servers' => [['url' => '/api/v1']],
            'tags' => array_values($tags),
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'JWT emitido por Keycloak. Roles: ADMIN, ANFITRION, CLIENTE.',
                    ],
                    'webhookSecret' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-Webhook-Secret',
                        'description' => 'Secreto compartido con la pasarela de pagos.',
                    ],
                ],
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'required' => ['codigo', 'mensaje'],
                        'properties' => [
                            'codigo' => ['type' => 'string', 'example' => 'regla_de_negocio'],
                            'mensaje' => ['type' => 'string'],
                        ],
                    ],
                    'Respuesta' => [
                        'type' => 'object',
                        'properties' => ['data' => ['nullable' => true, 'description' => 'El recurso o la lista pedida.']],
                    ],
                ],
            ],
        ];
    }

    /** @return list<Route> */
    private function rutas(): array
    {
        $rutas = array_filter(
            $this->router->getRoutes()->getRoutes(),
            static fn (Route $r): bool => str_starts_with($r->uri(), self::PREFIJO) && $r->getActionName() !== 'Closure',
        );
        usort($rutas, static fn (Route $a, Route $b): int => [$a->uri(), $a->methods()[0]] <=> [$b->uri(), $b->methods()[0]]);

        return array_values($rutas);
    }

    /** @return array{class-string, string} */
    private function accion(Route $ruta): array
    {
        $nombre = $ruta->getActionName();

        return str_contains($nombre, '@') ? explode('@', $nombre, 2) : [$nombre, '__invoke'];
    }

    private function paquete(string $clase): string
    {
        return explode('\\', $clase)[1] ?? 'General';
    }

    /** @return array<string, mixed> */
    private function operacion(Route $ruta, string $clase, string $metodo, string $paquete, string $verbo): array
    {
        $middleware = $ruta->gatherMiddleware();
        $roles = $this->roles($middleware);
        $idem = $this->idempotencia($middleware);
        $protegida = in_array('sesion', $middleware, true);
        $esWebhook = str_ends_with($ruta->uri(), 'notificaciones-pasarela');
        [$reglas, $requeridos] = $this->reglas($clase, $metodo);

        $op = [
            'tags' => [$paquete],
            'operationId' => lcfirst(class_basename($clase)).ucfirst($metodo),
            'summary' => $this->resumen($clase, $metodo),
        ];
        $doc = (new \ReflectionClass($clase))->getDocComment() ?: '';
        $descripcion = preg_match('/CU-[A-Z]{3}-\d{2}(?:(?:,| y| \(| \/)? ?(?:y )?\d{2})*/', $doc, $m) ? 'Casos de uso del controlador: '.trim($m[0]).'. ' : '';
        $descripcion .= $roles !== [] ? 'Roles permitidos: '.implode(', ', $roles).'.' : '';
        if (trim($descripcion) !== '') {
            $op['description'] = trim($descripcion);
        }
        $op['security'] = $protegida ? [['bearerAuth' => []]] : ($esWebhook ? [['webhookSecret' => []]] : []);

        $parametros = [];
        foreach ($ruta->parameterNames() as $nombre) {
            $parametros[] = ['name' => $nombre, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']];
        }
        if ($idem !== null) {
            $parametros[] = [
                'name' => 'Idempotency-Key', 'in' => 'header', 'required' => $idem === 'exigir',
                'schema' => ['type' => 'string', 'maxLength' => 100],
                'description' => 'Misma clave y mismo cuerpo devuelven la primera respuesta; con otro cuerpo, 422.',
            ];
        }
        if ($verbo === 'get') {
            foreach ($reglas as $campo => $schema) {
                $parametros[] = ['name' => $campo, 'in' => 'query', 'required' => isset($requeridos[$campo]), 'schema' => $schema];
            }
        }
        if ($parametros !== []) {
            $op['parameters'] = $parametros;
        }

        if ($verbo !== 'get' && $reglas !== []) {
            $op['requestBody'] = [
                'required' => $requeridos !== [],
                'content' => ['application/json' => ['schema' => array_filter([
                    'type' => 'object',
                    'required' => array_keys($requeridos),
                    'properties' => $reglas,
                ])]],
            ];
        }

        $exito = in_array($metodo, self::CREAN, true) ? '201' : '200';
        $op['responses'] = [$exito => [
            'description' => $exito === '201' ? 'Creado' : 'Correcto',
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Respuesta']]],
        ]];
        if ($protegida) {
            $op['responses']['401'] = $this->error('Falta el token o es invalido');
        }
        if ($roles !== [] || $esWebhook) {
            $op['responses']['403'] = $this->error('El rol no puede hacer esto o el recurso no es suyo');
        }
        if ($ruta->parameterNames() !== [] || $esWebhook) {
            $op['responses']['404'] = $this->error('El recurso no existe');
        }
        if ($verbo !== 'get') {
            $op['responses']['409'] = $this->error('Choca con el estado actual del recurso');
        }
        $op['responses']['422'] = $this->error('Datos invalidos o regla de negocio violada');

        return $op;
    }

    /** @return array<string, mixed> */
    private function error(string $descripcion): array
    {
        return [
            'description' => $descripcion,
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
        ];
    }

    /**
     * @param  list<string>  $middleware
     * @return list<string>
     */
    private function roles(array $middleware): array
    {
        foreach ($middleware as $m) {
            if (str_starts_with($m, 'rol:')) {
                return explode(',', substr($m, 4));
            }
        }

        return [];
    }

    /** @param list<string> $middleware */
    private function idempotencia(array $middleware): ?string
    {
        foreach ($middleware as $m) {
            if ($m === 'idempotente') {
                return 'opcional';
            }
            if (str_starts_with($m, 'idempotente:')) {
                return substr($m, 12);
            }
        }

        return null;
    }

    private function resumen(string $clase, string $metodo): string
    {
        $doc = (new \ReflectionClass($clase))->getDocComment() ?: '';
        $cu = preg_match('/(CU-[A-Z]{3}-\d{2}[^.\n]*)/', $doc, $m) ? ' ('.trim($m[1], ' *').')' : '';

        $palabras = strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $metodo));

        return ucfirst($palabras).' — '.class_basename($clase);
    }

    /**
     * Lee el `validate([...])` del metodo y lo traduce a esquemas JSON.
     *
     * @return array{array<string, array<string, mixed>>, array<string, true>}
     */
    private function reglas(string $clase, string $metodo): array
    {
        if (! method_exists($clase, $metodo)) {
            return [[], []];
        }
        $r = new ReflectionMethod($clase, $metodo);
        $fuente = implode('', array_slice(file((string) $r->getFileName()) ?: [], $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));

        if (! preg_match('/validate\(\s*\[(.*?)\]\s*\)/s', $fuente, $m)) {
            return [[], []];
        }

        $esquemas = [];
        $requeridos = [];
        preg_match_all('/\'([a-z_.*]+)\'\s*=>\s*\'([^\']+)\'/', $m[1], $pares, PREG_SET_ORDER);
        foreach ($pares as [, $campo, $reglas]) {
            if (str_contains($campo, '*')) {
                continue;
            }
            $lista = explode('|', $reglas);
            if (array_intersect($lista, ['required', 'present']) !== []) {
                $requeridos[$campo] = true;
            }
            $esquemas[$campo] = $this->esquema($lista, isset($pares) ? $campo : '');
        }

        return [$esquemas, $requeridos];
    }

    /**
     * @param  list<string>  $reglas
     * @return array<string, mixed>
     */
    private function esquema(array $reglas, string $campo): array
    {
        $s = ['type' => 'string'];
        foreach ($reglas as $regla) {
            [$nombre, $arg] = array_pad(explode(':', $regla, 2), 2, null);
            switch ($nombre) {
                case 'integer':
                    $s['type'] = 'integer';
                    break;
                case 'numeric':
                    $s['type'] = 'number';
                    break;
                case 'boolean':
                    $s['type'] = 'boolean';
                    break;
                case 'array':
                    $s = ['type' => 'array', 'items' => ['type' => 'string']];
                    break;
                case 'uuid':
                    $s['format'] = 'uuid';
                    break;
                case 'date':
                    $s['format'] = 'date-time';
                    break;
                case 'in':
                    $s['enum'] = explode(',', (string) $arg);
                    break;
                case 'size':
                    $s['minLength'] = $s['maxLength'] = (int) $arg;
                    break;
                case 'max':
                    $s[$s['type'] === 'string' ? 'maxLength' : 'maximum'] = (int) $arg;
                    break;
                case 'min':
                    $s[$s['type'] === 'string' ? 'minLength' : 'minimum'] = (int) $arg;
                    break;
            }
        }
        if (in_array('nullable', $reglas, true)) {
            $s['nullable'] = true;
        }

        return $s;
    }
}
