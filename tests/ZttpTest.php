<?php

use Zttp\Zttp;
use Zttp\ZttpResponse;
use PHPUnit\Framework\TestCase;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

class ZttpTest extends TestCase
{
    use ArraySubsetAsserts;
    
    public static function setUpBeforeClass(): void
    {
        ZttpServer::start();
    }

    public function url($url)
    {
        return vsprintf('%s/%s', [
            'http://localhost:' . getenv('TEST_SERVER_PORT'),
            ltrim($url, '/'),
        ]);
    }

    /** @test */
    public function query_parameters_can_be_passed_as_an_array()
    {
        $response = Zttp::get($this->url('/get'), [
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        self::assertArraySubset([
            'query' => [
                'foo' => 'bar',
                'baz' => 'qux',
            ]
        ], $response->json());
    }

    /** @test */
    public function query_parameters_in_urls_are_respected()
    {
        $response = Zttp::get($this->url('/get?foo=bar&baz=qux'));

        self::assertArraySubset([
            'query' => [
                'foo' => 'bar',
                'baz' => 'qux',
            ]
        ], $response->json());
    }

    /** @test */
    public function query_parameters_in_urls_can_be_combined_with_array_parameters()
    {
        $response = Zttp::get($this->url('/get?foo=bar'), [
            'baz' => 'qux'
        ]);

        self::assertArraySubset([
            'query' => [
                'foo' => 'bar',
                'baz' => 'qux',
            ]
        ], $response->json());
    }

    /** @test */
    public function options_can_be_set_all_at_once()
    {
        $response = Zttp::withOptions([
            'headers' => [
                'accept' => ['text/xml'],
            ]
        ])->get($this->url('/get'));

        self::assertArraySubset([
            'headers' => [
                'accept' => ['text/xml'],
            ]
        ], $response->json());
    }

    /** @test */
    public function post_content_is_json_by_default()
    {
        $response = Zttp::post($this->url('/post'), [
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        self::assertArraySubset([
            'headers' => [
                'content-type' => ['application/json'],
            ],
            'json' => [
                'foo' => 'bar',
                'baz' => 'qux',
            ]
        ], $response->json());
    }

    /** @test */
    public function post_content_can_be_sent_as_form_params()
    {
        $response = Zttp::asFormParams()->post($this->url('/post'), [
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        self::assertArraySubset([
            'headers' => [
                'content-type' => ['application/x-www-form-urlencoded'],
            ],
            'form_params' => [
                'foo' => 'bar',
                'baz' => 'qux',
            ]
        ], $response->json());
    }

    /** @test */
    public function post_content_can_be_sent_as_multipart()
    {
        $response = Zttp::asMultipart()->post($this->url('/multi-part'), [
            [
                'name' => 'foo',
                'contents' => 'bar'
            ],
            [
                'name' => 'baz',
                'contents' => 'qux',
            ],
            [
                'name' => 'test-file',
                'contents' => 'test contents',
                'filename' => 'test-file.txt',
            ],
        ])->json();

        $this->assertEquals(['foo' => 'bar', 'baz' => 'qux'], $response['body_content']);
        $this->assertTrue($response['has_file']);
        $this->assertEquals($response['file_content'], 'test contents');
        $this->assertStringStartsWith('multipart', $response['headers']['content-type'][0]);
    }

    /** @test */
    public function post_content_can_be_sent_as_json_explicitly()
    {
        $response = Zttp::asJson()->post($this->url('/post'), [
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        self::assertArraySubset([
            'headers' => [
                'content-type' => ['application/json'],
            ],
            'json' => [
                'foo' => 'bar',
                'baz' => 'qux',
            ]
        ], $response->json());
    }

    /** @test */
    public function get_with_additional_headers()
    {
        $response = Zttp::withHeaders(['Custom' => 'Header'])->get($this->url('/get'));

        self::assertArraySubset([
            'headers' => [
                'custom' => ['Header'],
            ],
        ], $response->json());
    }

    /** @test */
    public function post_with_additional_headers()
    {
        $response = Zttp::withHeaders(['Custom' => 'Header'])->post($this->url('/post'));

        self::assertArraySubset([
            'headers' => [
                'custom' => ['Header'],
            ],
        ], $response->json());
    }

    /** @test */
    public function the_accept_header_can_be_set_via_shortcut()
    {
        $response = Zttp::accept('banana/sandwich')->post($this->url('/post'));

        self::assertArraySubset([
            'headers' => [
                'accept' => ['banana/sandwich'],
            ],
        ], $response->json());
    }

    /** @test */
    public function exceptions_are_not_thrown_for_40x_responses()
    {
        $response = Zttp::withHeaders(['Z-Status' => 418])->get($this->url('/get'));

        $this->assertEquals(418, $response->status());
    }

    /** @test */
    public function exceptions_are_not_thrown_for_50x_responses()
    {
        $response = Zttp::withHeaders(['Z-Status' => 508])->get($this->url('/get'));

        $this->assertEquals(508, $response->status());
    }

    /** @test */
    public function redirects_are_followed_by_default()
    {
        $response = Zttp::get($this->url('/redirect'));

        $this->assertEquals(200, $response->status());
        $this->assertEquals('Redirected!', $response->body());
    }

    /** @test */
    public function redirects_can_be_disabled()
    {
        $response = Zttp::withoutRedirecting()->get($this->url('/redirect'));

        $this->assertEquals(302, $response->status());
        $this->assertEquals($this->url('/redirected'), $response->header('Location'));
    }

    /** @test */
    public function patch_requests_are_supported()
    {
        $response = Zttp::patch($this->url('/patch'), [
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        self::assertArraySubset([
            'json' => [
                'foo' => 'bar',
                'baz' => 'qux',
            ]
        ], $response->json());
    }

    /** @test */
    public function put_requests_are_supported()
    {
        $response = Zttp::put($this->url('/put'), [
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        self::assertArraySubset([
            'json' => [
                'foo' => 'bar',
                'baz' => 'qux',
            ]
        ], $response->json());
    }

    /** @test */
    public function delete_requests_are_supported()
    {
        $response = Zttp::delete($this->url('/delete'), [
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        self::assertArraySubset([
            'json' => [
                'foo' => 'bar',
                'baz' => 'qux',
            ]
        ], $response->json());
    }

    /** @test */
    public function query_parameters_are_respected_in_post_requests()
    {
        $response = Zttp::post($this->url('/post?banana=sandwich'), [
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        self::assertArraySubset([
            'query' => [
                'banana' => 'sandwich',
            ],
            'json' => [
                'foo' => 'bar',
                'baz' => 'qux',
            ]
        ], $response->json());
    }

    /** @test */
    public function query_parameters_are_respected_in_put_requests()
    {
        $response = Zttp::put($this->url('/put?banana=sandwich'), [
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        self::assertArraySubset([
            'query' => [
                'banana' => 'sandwich',
            ],
            'json' => [
                'foo' => 'bar',
                'baz' => 'qux',
            ]
        ], $response->json());
    }

    /** @test */
    public function query_parameters_are_respected_in_patch_requests()
    {
        $response = Zttp::patch($this->url('/patch?banana=sandwich'), [
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        self::assertArraySubset([
            'query' => [
                'banana' => 'sandwich',
            ],
            'json' => [
                'foo' => 'bar',
                'baz' => 'qux',
            ]
        ], $response->json());
    }

    /** @test */
    public function query_parameters_are_respected_in_delete_requests()
    {
        $response = Zttp::delete($this->url('/delete?banana=sandwich'), [
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        self::assertArraySubset([
            'query' => [
                'banana' => 'sandwich',
            ],
            'json' => [
                'foo' => 'bar',
                'baz' => 'qux',
            ]
        ], $response->json());
    }

    /** @test */
    public function can_retrieve_the_raw_response_body()
    {
        $response = Zttp::get($this->url('/simple-response'));

        $this->assertEquals("A simple string response", $response->body());
    }

    /** @test */
    public function can_retrieve_response_header_values()
    {
        $response = Zttp::get($this->url('/get'));

        $this->assertEquals('application/json', $response->header('Content-Type'));
        $this->assertEquals('application/json', $response->headers()['Content-Type']);
    }

    /** @test */
    public function can_check_if_a_response_is_success()
    {
        $response = Zttp::withHeaders(['Z-Status' => 200])->get($this->url('/get'));

        $this->assertTrue($response->isSuccess());
        $this->assertFalse($response->isRedirect());
        $this->assertFalse($response->isClientError());
        $this->assertFalse($response->isServerError());
    }

    /** @test */
    public function can_check_if_a_response_is_redirect()
    {
        $response = Zttp::withHeaders(['Z-Status' => 302])->get($this->url('/get'));

        $this->assertTrue($response->isRedirect());
        $this->assertFalse($response->isSuccess());
        $this->assertFalse($response->isClientError());
        $this->assertFalse($response->isServerError());
    }

    /** @test */
    public function can_check_if_a_response_is_client_error()
    {
        $response = Zttp::withHeaders(['Z-Status' => 404])->get($this->url('/get'));

        $this->assertTrue($response->isClientError());
        $this->assertFalse($response->isSuccess());
        $this->assertFalse($response->isRedirect());
        $this->assertFalse($response->isServerError());
    }

    /** @test */
    public function can_check_if_a_response_is_server_error()
    {
        $response = Zttp::withHeaders(['Z-Status' => 508])->get($this->url('/get'));

        $this->assertTrue($response->isServerError());
        $this->assertFalse($response->isSuccess());
        $this->assertFalse($response->isRedirect());
        $this->assertFalse($response->isClientError());
    }

    /** @test */
    public function is_ok_is_an_alias_for_is_success()
    {
        $response = Zttp::withHeaders(['Z-Status' => 200])->get($this->url('/get'));

        $this->assertTrue($response->isOk());
        $this->assertTrue($response->isSuccess());
        $this->assertFalse($response->isRedirect());
        $this->assertFalse($response->isClientError());
        $this->assertFalse($response->isServerError());
    }

    /** @test */
    public function multiple_callbacks_can_be_run_before_sending_the_request()
    {
        $state = [];

        $response = Zttp::beforeSending(function ($request) use (&$state) {
            return tap($request, function ($request) use (&$state) {
                $state['url'] = $request->url();
                $state['method'] = $request->method();
            });
        })->beforeSending(function ($request) use (&$state) {
            return tap($request, function ($request) use (&$state) {
                $state['headers'] = $request->headers();
                $state['body'] = $request->body();
            });
        })->withHeaders(['Z-Status' => 200])->post($this->url('/post'), ['foo' => 'bar']);

        $this->assertEquals($this->url('/post'), $state['url']);
        $this->assertEquals('POST', $state['method']);
        $this->assertArrayHasKey('User-Agent', $state['headers']);
        $this->assertEquals(200, $state['headers']['Z-Status']);
        $this->assertEquals(json_encode(['foo' => 'bar']), $state['body']);
    }

    /** @test */
    public function response_can_use_macros()
    {
        ZttpResponse::macro('testMacro', function () {
            return vsprintf('%s %s', [
                $this->json()['json']['foo'],
                $this->json()['json']['baz'],
            ]);
        });

        $response = Zttp::post($this->url('/post'), [
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        $this->assertEquals('bar qux', $response->testMacro());
    }

    /** @test */
    public function can_use_basic_auth()
    {
        $response = Zttp::withBasicAuth('zttp', 'secret')->get($this->url('/basic-auth'));

        $this->assertTrue($response->isOk());
    }

    /** @test */
    public function can_use_digest_auth()
    {
        $response = Zttp::withDigestAuth('zttp', 'secret')->get($this->url('/digest-auth'));

        $this->assertTrue($response->isOk());
    }

    /** @test */
    public function can_retrieve_effective_uri()
    {
        $response = Zttp::get($this->url('/redirect'));

        $this->assertEquals($this->url('/redirected'), $response->effectiveUri());
    }

    /**
     * @test
     * @expectedException \Zttp\ConnectionException
     */
    public function client_will_force_timeout()
    {
        Zttp::timeout(1)->get($this->url('/timeout'));
    }

    /** @test */
    public function cookies_can_be_shared_between_requests()
    {
        $response = Zttp::get($this->url('/set-cookie'));
        $response = Zttp::withCookies($response->cookies())->get($this->url('/get'));
        $this->assertEquals(['foo' => 'bar'], $response->json()['cookies']);

        $response = Zttp::withCookies($response->cookies())->get($this->url('/set-another-cookie'));
        $response = Zttp::withCookies($response->cookies())->get($this->url('/get'));
        $this->assertEquals(['foo' => 'bar', 'baz' => 'qux'], $response->json()['cookies']);

        $response = Zttp::get($this->url('/get'));
        $this->assertEquals([], $response->json()['cookies']);
    }
}

class ZttpServer
{
    public static function start()
    {
        $pid = exec('php -S ' . 'localhost:' . getenv('TEST_SERVER_PORT') . ' -t ./tests/server/public > /dev/null 2>&1 & echo $!');

        while (@file_get_contents('http://localhost:' . getenv('TEST_SERVER_PORT') . '/get') === false) {
            usleep(1000);
        }

        register_shutdown_function(function () use ($pid) {
            exec('kill ' . $pid);
        });
    }
}
