<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use PHPUnit\Framework\TestCase;
use Vatly\Fluent\Configuration\ArrayConfiguration;
use Vatly\Fluent\Vatly;
use Vatly\Fluent\Wiring;

/**
 * Sends a real request to a PHP built-in web server on 127.0.0.1 that echoes
 * the User-Agent it received (see Fixtures/user-agent-echo-server.php).
 */
class UserAgentTest extends TestCase
{
    /** @var resource|null */
    private static $server;

    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        self::$server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__.'/Fixtures/user-agent-echo-server.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        self::$baseUrl = "http://127.0.0.1:{$port}";

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $port);

            if ($connection !== false) {
                fclose($connection);

                return;
            }

            usleep(50000);
        }

        self::fail('The PHP built-in web server did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    public function test_the_user_agent_ends_with_this_packages_name_and_version(): void
    {
        $vatly = new Vatly(new Wiring(
            config: new ArrayConfiguration([
                'api_key' => 'test_abcdefghijklmnopqrstuvwxyz',
                'api_url' => self::$baseUrl,
            ]),
        ));

        $userAgent = $vatly->getApiClient()->performHttpCall('GET', 'user-agent')->userAgent;

        $this->assertStringEndsWith(' PHP/'.phpversion().' Curl/* vatly/vatly-fluent-php/'.Vatly::VERSION, $userAgent);
    }
}
