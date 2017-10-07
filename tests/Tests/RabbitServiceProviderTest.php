<?php

namespace TH\RabbitmqProvider\Tests\Silex\Provider;

use TH\RabbitmqProvider\RabbitServiceProvider;
use OldSound\RabbitMqBundle\RabbitMq\Consumer;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use OldSound\RabbitMqBundle\RabbitMq\RpcServer;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Pimple\Container;

class RabbitServiceProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testConnectionsAreRegistered()
    {
        $container = new Container();

        $container->register(new RabbitServiceProvider(), [
            "rabbit.connections" => $this->givenValidConnectionDefinitions()
        ]);

        $this->assertInstanceOf(AMQPStreamConnection::class, $container["rabbit.connection"]["default"]);
        $this->assertInstanceOf(AMQPStreamConnection::class, $container["rabbit.connection"]["another"]);
    }

    public function testProducersAreRegistered()
    {
        $container = new Container();

        $container->register(new RabbitServiceProvider(), [
            "rabbit.connections" => $this->givenValidConnectionDefinitions(),
            "rabbit.producers" => [
                "a_producer" => [
                    "connection"        => "default",
                    "exchange_options"  => ["name" => "a_exchange", "type" => "topic"]
                ],
                "second_producer" => [
                    "connection"        => "default",
                    "exchange_options"  => ["name" => "a_exchange", "type" => "topic"]
                ],
            ]
        ]);

        $this->assertInstanceOf(Producer::class, $container["rabbit.producer"]["a_producer"]);
        $this->assertInstanceOf(Producer::class, $container["rabbit.producer"]["second_producer"]);
    }

    public function testConsumersAreRegistered()
    {
        $container = new Container();

        $container["debug"] = function() {
            return true;
        };

        $container->register(new RabbitServiceProvider(), [
            "rabbit.connections" => $this->givenValidConnectionDefinitions(),
            "rabbit.consumers" => [
                "a_consumer" => [
                    "connection"        => "default",
                    "exchange_options"  => ["name" => "a_exchange","type" => "topic"],
                    "queue_options"     => ["name" => "a_queue", "routing_keys" => ["foo.#"]],
                    "callback"          => "debug"
                ],
                "second_consumer" => [
                    "connection"        => "another",
                    "exchange_options"  => ["name" => "a_exchange","type" => "topic"],
                    "queue_options"     => ["name" => "a_queue", "routing_keys" => ["#.foo.#"]],
                    "callback"          => "debug"
                ],
            ]
        ]);

        $this->assertInstanceOf(Consumer::class, $container["rabbit.consumer"]["a_consumer"]);
        $this->assertInstanceOf(Consumer::class, $container["rabbit.consumer"]["second_consumer"]);
    }

    public function testAnonymousConsumersAreRegistered()
    {
        $container = new Container();

        $container["debug"] = function() {
            return true;
        };

        $container->register(new RabbitServiceProvider(), [
            "rabbit.connections" => $this->givenValidConnectionDefinitions(),
            "rabbit.anon_consumers" => [
                "anoymous" => [
                    "connection"        => "another",
                    "exchange_options"  => ["name" => "exchange_name","type" => "topic"],
                    "callback"          => "debug"
                ]
            ]
        ]);

        $this->assertInstanceOf(Consumer::class, $container["rabbit.anonymous_consumer"]["anoymous"]);
    }

    public function testMultiplesConsumersAreRegistered()
    {
        $container = new Container();

        $container["debug"] = function() {
            return true;
        };

        $container->register(new RabbitServiceProvider(), [
            "rabbit.connections" => $this->givenValidConnectionDefinitions(),
            "rabbit.multiple_consumers" => [
                "multiple" => [
                    "connection"        => "default",
                    "exchange_options"  => ["name" => "exchange_name","type" => "topic"],
                    "queues"            => [
                        "exchange_name" => ["name" => "queue_name", "routing_keys" => ["foo.#"], "callback" => "debug"]
                    ]
                ]
            ]
        ]);

        $this->assertInstanceOf(Consumer::class, $container["rabbit.multiple_consumer"]["multiple"]);
    }

    public function testRpcClientsAreRegistered()
    {
        $container = new Container();

        $container->register(new RabbitServiceProvider(), [
            "rabbit.connections" => $this->givenValidConnectionDefinitions(),
            "rabbit.rpc_clients" => [
                "a_client" => [
                    "connection"                    => "another",
                    "expect_serialized_response"    => false
                ]
            ]
        ]);

        $this->assertInstanceOf(RpcClient::class, $container["rabbit.rpc_client"]["a_client"]);
    }

    public function testRpcServersAreRegistered()
    {
        $container = new Container();

        $container["debug"] = function() {
            return true;
        };

        $container->register(new RabbitServiceProvider(), [
            "rabbit.connections" => $this->givenValidConnectionDefinitions(),
            "rabbit.rpc_servers" => [
                "a_server" => [
                    "connection"    => "another",
                    "callback"      => "debug",
                    "qos_options"   => ["prefetch_size" => 0, "prefetch_count" => 1, "global" => false]
                ]
            ]
        ]);

        $this->assertInstanceOf(RpcServer::class, $container["rabbit.rpc_server"]["a_server"]);
    }

    private function givenValidConnectionDefinitions()
    {
        return [
            "default" => [
                "host" => "localhost",
                "port" => 5672,
                "user" => "guest",
                "password" => "guest",
                "vhost" => "/"
            ],
            "another" => [
                "host" => "localhost",
                "port" => 5672,
                "user" => "guest",
                "password" => "guest",
                "vhost" => "/"
            ]
        ];
    }
}
