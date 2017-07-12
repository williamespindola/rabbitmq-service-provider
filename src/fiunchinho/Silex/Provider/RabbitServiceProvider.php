<?php

namespace fiunchinho\Silex\Provider;

use OldSound\RabbitMqBundle\RabbitMq\AnonConsumer;
use OldSound\RabbitMqBundle\RabbitMq\Consumer;
use OldSound\RabbitMqBundle\RabbitMq\MultipleConsumer;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use OldSound\RabbitMqBundle\RabbitMq\RpcServer;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class RabbitServiceProvider implements ServiceProviderInterface
{
    const DEFAULT_CONNECTION = 'default';

    public function register(Container $container)
    {
        $this->loadConnections($container);
        $this->loadProducers($container);
        $this->loadConsumers($container);
        $this->loadAnonymousConsumers($container);
        $this->loadMultipleConsumers($container);
        $this->loadRpcClients($container);
        $this->loadRpcServers($container);
    }

    /**
     * @param Container $container
     * @throws \InvalidArgumentException
     */
    private function loadConnections(Container $container)
    {
        $container['rabbit.connection'] = function ($container) {
            if (!isset($container['rabbit.connections'])) {
                throw new \InvalidArgumentException('You need to specify at least a connection in your configuration.');
            }

            $connections = [];

            foreach ($container['rabbit.connections'] as $name => $options) {
                $connectionClass = isset($options['lazy']) && $options['lazy'] ? AMQPLazyConnection::class : AMQPConnection::class;
                $connectionTimeout = isset($options['connection_timeout']) ? $options['connection_timeout'] : 3;
                $readWriteTimeout = isset($options['read_write_timeout']) ? $options['read_write_timeout'] : 6;
                $keepalive = isset($options['keepalive']) ? $options['keepalive'] : false;
                $heartbeat = isset($options['heartbeat']) ? $options['heartbeat'] : 3;

                $connection = new $connectionClass(
                    $options['host'],
                    $options['port'],
                    $options['user'],
                    $options['password'],
                    $options['vhost'],
                    false,
                    'AMQPLAIN',
                    null,
                    'en_US',
                    $connectionTimeout,
                    $readWriteTimeout,
                    null,
                    $keepalive,
                    $heartbeat
                );

                $connections[$name] = $connection;
            }

            return $connections;
        };
    }

    /**
     * @param Container $container
     * @param array $options
     * @param array $connections
     * @return AMQPLazyConnection|AMQPConnection
     * @throws \InvalidArgumentException
     */
    private function getConnection(Container $container, $options, $connections)
    {
        $connection_name = $options['connection']?: self::DEFAULT_CONNECTION;

        if (!isset($connections[$connection_name])) {
            throw new \InvalidArgumentException('Configuration for connection [' . $connection_name . '] not found');
        }

        return $container['rabbit.connection'][$connection_name];
    }

    /**
     * @param Container $container
     */
    private function loadProducers(Container $container)
    {
        $container['rabbit.producer'] = function ($container) {
            if (!isset($container['rabbit.producers'])) {
                return null;
            }

            $producers = [];

            foreach ($container['rabbit.producers'] as $name => $options) {
                $connection = $this->getConnection($container, $options, $container['rabbit.connections']);

                $producer = new Producer($connection);
                $producer->setExchangeOptions($options['exchange_options']);

                //this producer doesn't define a queue
                if (!isset($options['queue_options'])) {
                    $options['queue_options']['name'] = null;
                }
                $producer->setQueueOptions($options['queue_options']);

                if ((array_key_exists('auto_setup_fabric', $options)) && (!$options['auto_setup_fabric'])) {
                    $producer->disableAutoSetupFabric();
                }

                $producers[$name] = $producer;
            }

            return $producers;
        };
    }

    /**
     * @param Container $container
     */
    private function loadConsumers(Container $container)
    {
        $container['rabbit.consumer'] = function ($container) {
            if (!isset($container['rabbit.consumers'])) {
                return null;
            }

            $consumers = [];
            
            foreach ($container['rabbit.consumers'] as $name => $options) {
                $connection = $this->getConnection($container, $options, $container['rabbit.connections']);
                $consumer = new Consumer($connection);
                $consumer->setExchangeOptions($options['exchange_options']);
                $consumer->setQueueOptions($options['queue_options']);
                $consumer->setCallback(array($container[$options['callback']], 'execute'));

                if (array_key_exists('qos_options', $options)) {
                    $consumer->setQosOptions(
                        $options['qos_options']['prefetch_size'],
                        $options['qos_options']['prefetch_count'],
                        $options['qos_options']['global']
                    );
                }

                if (array_key_exists('idle_timeout', $options)) {
                    $consumer->setIdleTimeout($options['idle_timeout']);
                }

                if ((array_key_exists('auto_setup_fabric', $options)) && (!$options['auto_setup_fabric'])) {
                    $consumer->disableAutoSetupFabric();
                }

                if (array_key_exists('graceful_max_execution', $options)) {
                    $grace = $options['graceful_max_execution'];
                    if (array_key_exists('timeout', $grace)) {
                        $consumer->setGracefulMaxExecutionDateTimeFromSecondsInTheFuture($grace['timeout']);
                    }
                    if (array_key_exists('exit_code', $grace)) {
                        $consumer->setGracefulMaxExecutionTimeoutExitCode($grace['exit_code']);
                    }
                }

                $consumers[$name] = $consumer;
            }

            return $consumers;
        };
    }

    /**
     * @param Container $container
     */
    private function loadAnonymousConsumers(Container $container)
    {
        $container['rabbit.anonymous_consumer'] = function ($container) {
            if (!isset($container['rabbit.anon_consumers'])) {
                return null;
            }

            $consumers = [];

            foreach ($container['rabbit.anon_consumers'] as $name => $options) {
                $connection = $this->getConnection($container, $options, $container['rabbit.connections']);
                $consumer = new AnonConsumer($connection);
                $consumer->setExchangeOptions($options['exchange_options']);
                $consumer->setCallback(array($container[$options['callback']], 'execute'));

                $consumers[$name] = $consumer;
            }

            return $consumers;
        };
    }

    /**
     * @param Container $container
     */
    private function loadMultipleConsumers(Container $container)
    {
        $container['rabbit.multiple_consumer'] = function ($container) {
            if (!isset($container['rabbit.multiple_consumers'])) {
                return null;
            }

            $consumers = [];

            foreach ($container['rabbit.multiple_consumers'] as $name => $options) {
                $connection = $this->getConnection($container, $options, $container['rabbit.connections']);
                $consumer = new MultipleConsumer($connection);
                $consumer->setExchangeOptions($options['exchange_options']);

                foreach ($options['queues'] as &$queue) {
                    if (isset($queue['callback'])) {
                        $queue['callback'] = array($container[$queue['callback']], 'execute');
                    }
                }

                $consumer->setQueues($options['queues']);

                if (array_key_exists('qos_options', $options)) {
                    $consumer->setQosOptions(
                        $options['qos_options']['prefetch_size'],
                        $options['qos_options']['prefetch_count'],
                        $options['qos_options']['global']
                    );
                }

                if (array_key_exists('idle_timeout', $options)) {
                    $consumer->setIdleTimeout($options['idle_timeout']);
                }

                if ((array_key_exists('auto_setup_fabric', $options)) && (!$options['auto_setup_fabric'])) {
                    $consumer->disableAutoSetupFabric();
                }

                $consumers[$name] = $consumer;
            }

            return $consumers;
        };
        
    }

    /**
     * @param Container $container
     */
    private function loadRpcClients(Container $container)
    {
        $container['rabbit.rpc_client'] = function ($container) {
            if (!isset($container['rabbit.rpc_clients'])) {
                return null;
            }

            $clients = [];

            foreach ($container['rabbit.rpc_clients'] as $name => $options) {
                $connection = $this->getConnection($container, $options, $container['rabbit.connections']);
                $client = new RpcClient($connection);

                if (array_key_exists('expect_serialized_response', $options)) {
                    $client->initClient($options['expect_serialized_response']);
                }

                $clients[$name] = $client;
            }

            return $clients;
        };
    }

    /**
     * @param Container $container
     */
    private function loadRpcServers(Container $container)
    {
        $container['rabbit.rpc_server'] = function ($container) {
            if (!isset($container['rabbit.rpc_servers'])) {
                return null;
            }

            $servers = [];

            foreach ($container['rabbit.rpc_servers'] as $name => $options) {
                $connection = $this->getConnection($container, $options, $container['rabbit.connections']);
                $server = new RpcServer($connection);
                $server->initServer($name);
                $server->setCallback(array($container[$options['callback']], 'execute'));

                if (array_key_exists('qos_options', $options)) {
                    $server->setQosOptions(
                        $options['qos_options']['prefetch_size'],
                        $options['qos_options']['prefetch_count'],
                        $options['qos_options']['global']
                    );
                }

                $servers[$name] = $server;
            }

            return $servers;
        };
        
    }
}
