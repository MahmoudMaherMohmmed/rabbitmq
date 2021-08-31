<?php

require(__DIR__.'/vendor/autoload.php');

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

$exchange = 'subscribers';
$queue = 'subscribers_accounts';

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

/*
    The following code is the same both in the consumer and the producer.
    In this way we are sure we always have a queue to consume from and an
        exchange where to publish messages.
*/

/*
    name: $queue
    passive: false //if the queue is already exist return an error
    durable: true // the queue will survive server restarts(if true contine from stored progress else start the begining)(Metadata of a durable queue is stored on disk, Durable queues will be recovered on node boot, including messages in them published as persistent)
    exclusive: false // the queue can be accessed in other channels
    auto_delete: false //the queue won't be deleted once the channel is closed.
*/
$channel->queue_declare($queue, false, true, false, false);

/*
    name: $exchange
    type: direct
    passive: false //if the queue is already exist return an error
    durable: true // the exchange will survive server restarts(Metadata of a durable queue is stored on disk, Durable queues will be recovered on node boot, including messages in them published as persistent)
    auto_delete: false //the exchange won't be deleted once the channel is closed.
*/

$channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);

$channel->queue_bind($queue, $exchange);

$faker = Faker\Factory::create();
for ($i = 0; $i < 100000; $i++) {
    $messageBody = json_encode(['name'=>$faker->name, 'email'=>$faker->email, 'phone'=>$faker->PhoneNumber(),  'address'=>$faker->address]);
    $message = new AMQPMessage($messageBody, ['content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
    $channel->basic_publish($message, $exchange);
}

echo 'Finish publishing to queue'.PHP_EOL;

$channel->close();
$connection->close();