<?php

namespace Tests\Feature\ClubPos;

use App\Http\Controllers\ClubPos\ReportController;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The sales report's hourly breakdown is raw SQL, so the "hour of day"
 * expression has to be chosen per engine. StockAndReportsTest covers the SQLite
 * path; Postgres is the cloud deployment target and has no HOUR() function, so
 * the report returned a 500 there until this branch existed. This locks in all
 * three engines.
 */
class SalesReportHourExpressionTest extends TestCase
{
    public function test_the_hour_expression_is_engine_specific(): void
    {
        $connections = [];
        foreach (['sqlite', 'pgsql', 'mysql'] as $driver) {
            $connections[] = new class($driver)
            {
                public function __construct(private string $driver) {}

                public function getDriverName(): string
                {
                    return $this->driver;
                }
            };
        }

        DB::shouldReceive('connection')->times(3)->andReturn(...$connections);

        $method = new ReflectionMethod(ReportController::class, 'hourExpression');
        $controller = new ReportController;

        $this->assertSame(
            "CAST(strftime('%H', closed_at) AS INTEGER)",
            $method->invoke($controller, 'closed_at'),
            'SQLite must keep using strftime.',
        );
        $this->assertSame(
            'CAST(EXTRACT(HOUR FROM closed_at) AS INTEGER)',
            $method->invoke($controller, 'closed_at'),
            'Postgres has no HOUR() function; the report 500s without EXTRACT.',
        );
        $this->assertSame(
            'HOUR(closed_at)',
            $method->invoke($controller, 'closed_at'),
            'MySQL stays the fallback engine.',
        );
    }
}
