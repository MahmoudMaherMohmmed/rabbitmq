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
5- bind queue to exchange with routing key
6- send message
7- close channel
8- close connection
*/

$exchange = 'food';
$meat_queue = 'meat';
$vegetables_queue = 'vegetables';
$meat_routing_key = 'meat';
$vegetables_routing_key = 'vegetables';

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

/*
    name: $exchange
    type: direct
    passive: false //don't check if an exchange with the same name exists
    durable: true // the exchange will survive server restarts
                  //(if true contine from stored progress else start the begining)
                 //(Metadata of a durable queue is stored on disk, Durable queues will be recovered on node boot, including messages in them published as persistent)
    auto_delete: false //the exchange won't be deleted once the channel is closed.
*/
$channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);

/*
    name: $queue
    passive: false //don't check if an queue with the same name exists
    durable: true // the queue will survive server restarts
                  //(if true contine from stored progress else start the begining)
                 //(Metadata of a durable queue is stored on disk, Durable queues will be recovered on node boot, including messages in them published as persistent)
    exclusive: false // the queue can be accessed in other channels
    auto_delete: false //the queue won't be deleted once the channel is closed.
*/
$channel->queue_declare($meat_queue, false, true, false, false);
$channel->queue_declare($vegetables_queue, false, true, false, false);


$channel->queue_bind($meat_queue, $exchange, $meat_routing_key);
$channel->queue_bind($vegetables_queue, $exchange, $vegetables_routing_key);

/* Messages marked as 'persistent' that are delivered to 'durable' queues will be logged to disk. Durable queues are recovered in the event of a crash */
$faker = Faker\Factory::create();
for ($i = 1; $i <= 100; $i++) {
    if($i % 2 == 0){
        $messageBody = json_encode(['name'=>$faker->name, 'email'=>$faker->email, 'phone'=>$faker->PhoneNumber(),  'meal'=>'meat_'.$i]);
        $message = new AMQPMessage($messageBody, ['content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $channel->basic_publish($message, $exchange, $meat_routing_key);
    }else{
        $messageBody = json_encode(['name'=>$faker->name, 'email'=>$faker->email, 'phone'=>$faker->PhoneNumber(),  'meal'=>'vegetables_'.$i]);
        $message = new AMQPMessage($messageBody, ['content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $channel->basic_publish($message, $exchange, $vegetables_routing_key);
    }
}

echo 'Finish publishing to queues'.PHP_EOL;

$channel->close();
$connection->close();