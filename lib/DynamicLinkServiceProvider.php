<?php

namespace DiagVN\DynamicLink;

use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class DynamicLinkServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes(
            [
                //file source => file destination below
                __DIR__ . '/config/dynamic_link.php' => config_path('dynamic_link.php'),
                //you can also add more configs here
            ]
        );
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Client::class, function () {
            return new Client(new GuzzleClient([
                'timeout' => config('dynamic_link.timeout'),
                'handler' => $this->stackRetry(config('dynamic_link.retry')),
            ]));
        });
    }

    private function stackRetry(int $retryTimes = 3): HandlerStack
    {
        /**
         * Listen for retry events
         *
         * @param int $attemptNumber How many attempts have been tried for this particular request
         * @param float $delay How long the client will wait before retrying the request
         * @param RequestInterface $request Request
         * @param array $options Guzzle request options
         * @param ResponseInterface|null $response Response (or NULL if response not sent; e.g. connect timeout)
         */
        $listener = function (int $attemptNumber, float $delay, RequestInterface &$request, array &$options, ?ResponseInterface $response) {
            Log::debug(
                sprintf(
                    "Retrying request to %s.  Server responded with %s.  Will wait %s seconds.  This is attempt #%s",
                    $request->getUri()->getPath(),
                    $response ? $response->getStatusCode() : 0,
                    number_format($delay, 2),
                    $attemptNumber
                )
            );
        };

        $stack = HandlerStack::create();
        $stack->push(GuzzleRetryMiddleware::factory([
            // Retry options
            'max_retry_attempts' => $retryTimes,
            'retry_on_status' => [400, 401, 500, 503, 502, 504],
            'retry_after_header' => 'Retry-After',
            'on_retry_callback' => $listener,
            'retry_on_timeout' => true,
        ]));

        return $stack;
    }
}
