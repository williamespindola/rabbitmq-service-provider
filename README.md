# RabbitMq Service Provider for Pimple / Silex 2 #

## About ##

This Pimple service provider incorporates the awesome [RabbitMqBundle](http://github.com/videlalvaro/RabbitMqBundle) into your application. Installing this bundle created by [Alvaro Videla](https://twitter.com/old_sound) you can use [RabbitMQ](http://www.rabbitmq.com/) messaging features in your application, using the [php-amqplib](http://github.com/videlalvaro/php-amqplib) library.

After installing this service provider, sending messages from a controller would be something like

```php
$app->post('/message', function(Request $request) use ($app){
    $producer = $app['rabbit.producer']['my_exchange_name'];
    $producer->publish('Some message');

    return new Response($msg_body);
});
```

Later when you want to consume 50 messages out of the queue names 'my_queue', you just run on the CLI:

```bash
$ ./app/console rabbitmq:consumer -m 50 my_queue
```

To learn what you can do with the bundle, please read the bundle's [README](https://github.com/videlalvaro/RabbitMqBundle/blob/master/README.md).

## Installation ##

Require the library with Composer:

```
$ composer require williamespindola/rabbitmq-service-provider
```

Then, to activate the service, register the service provider after creating your Pimple Container. With Silex 2:

```php

use Silex\Application;
use fiunchinho\Silex\Provider\RabbitServiceProvider;

$app = new Application();
$app->register(new RabbitServiceProvider());
```

Start sending messages ;)

## Usage ##

In the [README](https://github.com/videlalvaro/RabbitMqBundle/blob/master/README.md) file from the Symfony bundle you can see all the available options. For example, to configure our service with two different connections and a couple of producers, and one consumer, we will pass the following configuration:

```php
$app->register(new RabbitServiceProvider(), [
    'rabbit.connections' => [
        'default' => [
            'host'                  => 'localhost',
            'port'                  => 5672,
            'user'                  => 'guest',
            'password'              => 'guest',
            'vhost'                 => '/',
            'lazy'                  => false,
            'connection_timeout'    => 3,
            'read_write_timeout'    => 6,
            'keepalive'             => false,
            'heartbeat'             => 3
        ],
        'another' => [
            'host'                  => 'another_host',
            'port'                  => 5672,
            'user'                  => 'guest',
            'password'              => 'guest',
            'vhost'                 => '/'
            'lazy'                  => false,
            'connection_timeout'    => 3,
            'read_write_timeout'    => 6,
            'keepalive'             => false,
            'heartbeat'             => 3
        ]
    ],
    'rabbit.producers' => [
        'first_producer' => [
            'connection'        => 'another',
            'exchange_options'  => ['name' => 'a_exchange', 'type' => 'topic']
        ],
        'second_producer' => [
            'connection'        => 'default',
            'exchange_options'  => ['name' => 'a_exchange', 'type' => 'topic']
        ],
    ],
    'rabbit.consumers' => [
        'a_consumer' => [
            'connection'        => 'default',
            'exchange_options'  => ['name' => 'a_exchange','type' => 'topic'],
            'queue_options'     => ['name' => 'a_queue', 'routing_keys' => ['foo.#']],
            'callback'          => 'your_consumer_service',
            'graceful_max_execution' => [
                'timeout' => 900,
                'exit_code' => 10,
            ],
        ]
    ]
]);
```

Keep in mind that the callback that you choose in the consumer needs to be a service that has been registered in the Pimple container. Consumer services implement the ConsumerInterface, which has a execute() public method.

## Credits ##

- [RabbitMqBundle](https://github.com/php-amqplib/RabbitMqBundle) bundle, originally by [Alvaro Videla](https://twitter.com/old_sound)
- [rabbitmq-service-provider](https://github.com/e-Sixt/rabbitmq-service-provider) by fiunchinho & AntonStoeckl
