<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * حارس بنيوي: لا يمكن إضافة مسار يملكه مستخدم دون اختبار وصول أفقي،
 * وكل مسار مقيَّد بدور يرفض الأدوار الأخرى.
 */
class RouteGuardTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private const MATRIX_CLASSES = [
        CustomerApiAccessTest::class,
        SellerApiAccessTest::class,
        CustomerWebAccessTest::class,
        SellerWebAccessTest::class,
    ];

    private const ROLES = ['customer', 'seller', 'admin', 'employee'];

    /** مسارات ذات معامل لا يملكها مستخدم بعينه (مورد عام). المفتاح: "METHOD uri" والقيمة: السبب. */
    private const OWNER_AGNOSTIC = [
        'POST api/v1/favorites/{product}/toggle' => 'acts on a public product, owner-agnostic',
        'POST customer/favorites/{product}' => 'acts on a public product, owner-agnostic',
        'POST api/v1/products/{product}/questions' => 'any customer may ask on a public product',
        'POST customer/products/{product}/questions' => 'any customer may ask on a public product',
        'POST api/v1/products/{product}/reviews' => 'purchase rule covered by ReviewTest',
        'POST api/v1/questions/{question}/answers' => 'community answers on public questions',
        'POST customer/questions/{question}/answers' => 'community answers on public questions',
        'POST api/v1/questions/{question}/helpful' => 'vote on a public question',
        'POST api/v1/stores/{sellerProfile}/follow' => 'public store',
        'POST store/{sellerProfile}/follow' => 'public store',
        'POST api/v1/stores/{sellerProfile}/conversations' => "starts the caller's own conversation",
        'POST store/{sellerProfile}/message' => "starts the caller's own conversation",
        'POST api/v1/coupons/{campaign}/collect' => 'audience rules covered by CollectCouponTest',
        'POST coupons/{campaign}/collect' => 'audience rules covered by CollectCouponTest',
    ];

    /** مسارات لا يمكن استدعاؤها كمستخدم جلسة/Sanctum في اختبار، مع السبب. المفتاح: "METHOD uri". */
    private const VERTICAL_SKIP = [];

    /** مسارات ببادئة دور لكنها عامة عمداً (صفحة معلومات بعد التسجيل). */
    private const PREFIX_EXEMPT = [
        'GET employee/registration-pending' => 'public info page shown after employee sign-up, before approval',
    ];

    private function normalise(string $uri): string
    {
        $uri = preg_replace('/\{[^}]*\}/', '{}', trim($uri, '/'));

        // حالات البنك التجريبي تكتب النوع حرفياً (payment|card) بينما المسار المسجَّل {kind}.
        return preg_replace('#^sandbox-bank/(payment|card)/#', 'sandbox-bank/{}/', $uri);
    }

    /** @return list<array> */
    private function allCases(): array
    {
        $cases = [];
        foreach (self::MATRIX_CLASSES as $class) {
            foreach ((new ReflectionClass($class))->getReflectionConstants(\ReflectionClassConstant::IS_PUBLIC) as $const) {
                if (str_ends_with($const->getName(), 'CASES')) {
                    foreach ($const->getValue() as $case) {
                        $cases[] = $case;
                    }
                }
            }
        }

        return $cases;
    }

    /** @return list<string> الأساليب بدون HEAD */
    private function methodsOf(RoutingRoute $route): array
    {
        return array_values(array_filter($route->methods(), fn ($m) => $m !== 'HEAD'));
    }

    private function isAuth(string $middleware): bool
    {
        return str_starts_with($middleware, 'auth:')
            || $middleware === 'auth'
            || str_starts_with(ltrim($middleware, '\\'), 'Illuminate\\Auth\\Middleware\\Authenticate');
    }

    /** الأدوار المسموحة (تقاطع كل middleware من نوع role:) أو null إن لم يوجد. */
    private function allowedRoles(RoutingRoute $route): ?array
    {
        $allowed = null;
        foreach ($route->gatherMiddleware() as $mw) {
            if (is_string($mw) && str_starts_with($mw, 'role:')) {
                $roles = explode(',', substr($mw, 5));
                $allowed = $allowed === null ? $roles : array_values(array_intersect($allowed, $roles));
            }
        }

        return $allowed;
    }

    public function test_every_matrix_case_hits_a_registered_route(): void
    {
        $tenant = $this->tenant('guard');
        $bad = [];
        foreach ($this->allCases() as [$method, $template]) {
            $uri = $this->resolveUri($template, $tenant);
            try {
                $matched = Route::getRoutes()->match(Request::create($uri, $method));
            } catch (\Throwable $e) {
                $bad[] = "$method $template (no route: ".class_basename($e).')';

                continue;
            }
            if ($this->normalise($matched->uri()) !== $this->normalise($template)) {
                $bad[] = "$method $template (matched {$matched->uri()})";
            }
        }
        $this->assertSame([], $bad, "Matrix cases that hit no registered route:\n".implode("\n", $bad));
    }

    public function test_every_owner_scoped_route_is_in_a_matrix(): void
    {
        $covered = [];
        foreach ($this->allCases() as [$method, $template]) {
            $covered[$method.' '.$this->normalise($template)] = true;
        }
        $excluded = [];
        foreach (self::OWNER_AGNOSTIC as $key => $reason) {
            [$m, $u] = explode(' ', $key, 2);
            $excluded[$m.' '.$this->normalise($u)] = $key;
        }

        $uncovered = [];
        $usedExclusions = [];
        $count = 0;
        $coveredCount = 0;
        $excludedCount = 0;
        foreach (Route::getRoutes() as $route) {
            if (! str_contains($route->uri(), '{')) {
                continue;
            }
            $mws = $route->gatherMiddleware();
            if (! array_filter($mws, fn ($m) => is_string($m) && $this->isAuth($m))) {
                continue;
            }
            $allowed = $this->allowedRoles($route);
            if ($allowed !== null && array_diff($allowed, ['customer', 'seller'])) {
                continue;
            }
            $count++;
            $inMatrix = false;
            $inExcluded = false;
            foreach ($this->methodsOf($route) as $m) {
                $k = $m.' '.$this->normalise($route->uri());
                $inMatrix = $inMatrix || isset($covered[$k]);
                if (isset($excluded[$k])) {
                    $inExcluded = true;
                    $usedExclusions[$excluded[$k]] = true;
                }
            }
            if ($inMatrix) {
                $coveredCount++;
            } elseif ($inExcluded) {
                $excludedCount++;
            } else {
                $uncovered[] = implode('|', $this->methodsOf($route)).' '.$route->uri();
            }
        }

        $this->assertGreaterThan(0, $count);
        $this->assertSame([], $uncovered, "Owner-scoped routes in no matrix:\n".implode("\n", $uncovered));

        $stale = array_values(array_diff(array_keys(self::OWNER_AGNOSTIC), array_keys($usedExclusions)));
        $this->assertSame([], $stale, "Stale exclusion entries (match no registered owner-scoped route):\n".implode("\n", $stale));
    }

    /** مسار تحت بادئة دور (admin/seller/employee) يجب أن يحمل role: يتضمن ذلك الدور فقط، وإلا انكشف بالتجاور. */
    public function test_role_prefixed_routes_carry_the_matching_role_middleware(): void
    {
        $bad = [];
        foreach (Route::getRoutes() as $route) {
            $uri = preg_replace('#^api/v1/#', '', $route->uri());
            $prefix = explode('/', $uri)[0];
            if (! in_array($prefix, ['admin', 'seller', 'employee'], true)) {
                continue;
            }
            $allowed = $this->allowedRoles($route);
            if (isset(self::PREFIX_EXEMPT[($this->methodsOf($route)[0] ?? '').' '.$route->uri()])) {
                continue;
            }
            if ($allowed === null || ! in_array($prefix, $allowed, true)) {
                $bad[] = implode('|', $this->methodsOf($route)).' '.$route->uri().' (role: '.($allowed === null ? 'none' : implode(',', $allowed)).')';
            }
        }
        $this->assertSame([], $bad, "Role-prefixed routes without the matching role middleware:\n".implode("\n", $bad));
    }

    /** صف موجود من الموديل، وإلا ينشئه عبر الـ factory إن وُجد. */
    private function anyRow(string $class): ?Model
    {
        $row = $class::query()->first();
        if ($row === null && method_exists($class, 'factory')) {
            try {
                $row = $class::factory()->create();
            } catch (\Throwable) {
                $row = null;
            }
        }

        return $row;
    }

    public function test_role_gated_routes_reject_other_roles(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class, ThrottleRequests::class]);
        $tenant = $this->tenant('vert');
        $users = [];
        foreach (self::ROLES as $i => $role) {
            $users[$role] = User::create([
                'first_name' => ucfirst($role), 'last_name' => 'Vertical', 'phone' => '0977'.str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT),
                'email' => "$role-vertical@guard.test", 'password' => 'password', 'role' => $role, 'status' => 'active',
            ]);
        }

        $aliases = ['sellerProfile' => 'store', 'vendor' => 'store', 'staffConversation' => 'supportConversation'];
        $failures = [];
        $distribution = [];
        $calls = 0;
        $seen = [];
        $modelCache = [];
        $modelParams = [];

        foreach (Route::getRoutes() as $route) {
            $allowed = $this->allowedRoles($route);
            if ($allowed === null) {
                continue;
            }
            foreach ($route->signatureParameters(['subClass' => Model::class]) as $parameter) {
                $modelParams[$route->uri()][$parameter->getName()] = $parameter->getType()->getName();
            }
            $method = $this->methodsOf($route)[0] ?? null;
            if ($method === null) {
                continue;
            }
            $key = $method.' '.$route->uri();
            if (isset($seen[$key]) || isset(self::VERTICAL_SKIP[$key])) {
                continue;
            }
            $seen[$key] = true;

            $unresolved = [];
            $uri = '/'.preg_replace_callback('/\{(\w+)(?::\w+)?\??\}/', function ($m) use ($route, $tenant, $aliases, $modelParams, &$unresolved, &$modelCache) {
                $name = $m[1];
                $model = $tenant[$name] ?? $tenant[$aliases[$name] ?? ''] ?? null;
                if (! $model && isset($modelParams[$route->uri()][$name])) {
                    $class = $modelParams[$route->uri()][$name];
                    $model = $modelCache[$class] ??= $this->anyRow($class);
                    $field = $route->bindingFieldFor($name);
                    if ($model && $field) {
                        return (string) $model->getAttribute($field);
                    }
                }
                if ($model) {
                    return (string) $model->getRouteKey();
                }
                $unresolved[] = $name;

                return '1';
            }, $route->uri());
            $isApi = str_starts_with($route->uri(), 'api/');

            foreach (array_diff(self::ROLES, $allowed) as $role) {
                $this->actingAs($users[$role], $isApi ? 'sanctum' : 'web');
                $response = $isApi
                    ? $this->json($method, $uri)
                    : $this->call($method, $uri);
                $status = $response->getStatusCode();
                $calls++;
                $bucket = ($isApi ? 'api ' : 'web ').$status;
                $distribution[$bucket] = ($distribution[$bucket] ?? 0) + 1;
                if ($status === 404 && ! in_array(explode('/', preg_replace('#^api/v1/#', '', $route->uri()))[0], ['admin', 'seller', 'employee'], true)) {
                    $failures[] = "$role -> $method $uri (route {$route->uri()}): 404 before the role gate; unresolved params: ".($unresolved ? implode(',', $unresolved) : 'none (row missing or custom binding)');
                }
                if ($status >= 500 || ($status >= 200 && $status < 300)) {
                    $failures[] = "$role -> $method $uri (route {$route->uri()}): $status";
                }
            }
        }

        $this->assertGreaterThan(0, $calls);
        $this->assertSame([], $failures, "Role-gated routes that did not reject:\n".implode("\n", $failures));
    }
}
