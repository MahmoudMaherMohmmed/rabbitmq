<?php

require(__DIR__.'/vendor/autoload.php');

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

/*
0- setup rabbitMQ connecting 
1- create channel for new connection
2- define variavles and routing key
3- declear exchange
4- declear queue
5- bind queue to exchange
6- send message
7- close channel
8- close connection
*/

$exchange = 'restaurant';
$orders_queue = 'orders';
$casher_queue = 'casher';
$delivery_queue = 'delivery';

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

/*
    name: $exchange
    type: fanout
    passive: false // don't check if an exchange with the same name exists
    durable: false // the exchange will survive server restarts
                  //(if true contine from stored progress else start the begining)
                 //(Metadata of a durable exchange is stored on disk, Durable exchanges will be recovered on node boot, including messages in them published as persistent)
    auto_delete: true //the exchange will be deleted once the channel is closed.
*/
$channel->exchange_declare($exchange, AMQPExchangeType::FANOUT, false, false, true);

/*
    name: $queue
    passive: false //don't check if an queue with the same name exists
    durable: true // the queue will survive server restarts
                  //(if true contine from stored progress else start the begining)
                 //(Metadata of a durable queue is stored on disk, Durable queues will be recovered on node boot, including messages in them published as persistent)
    exclusive: false // the queue can be accessed in other channels
    auto_delete: false //the queue won't be deleted once the channel is closed.
*/
$channel->queue_declare($orders_queue, false, false, false, true);
$channel->queue_declare($casher_queue, false, false, false, true);
$channel->queue_declare($delivery_queue, false, false, false, true);

$channel->queue_bind($orders_queue, $exchange);
$channel->queue_bind($casher_queue, $exchange);
$channel->queue_bind($delivery_queue, $exchange);

$faker = Faker\Factory::create();
for ($i = 0; $i < 100; $i++) {
    $messageBody = json_encode(['name'=>$faker->name, 'email'=>$faker->email, 'phone'=>$faker->PhoneNumber(),  'address'=>$faker->address]);
    $message = new AMQPMessage($messageBody, array('content_type' => 'application/json'));
    $channel->basic_publish($message, $exchange);
}


$channel->close();
$connection->close();