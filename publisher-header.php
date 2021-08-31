<?php

require(__DIR__.'/vendor/autoload.php');

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire;

$exchange = 'school_header';
$manager_queue = 'manager';
$teacher_queue = 'teacher';
$student_queue = 'student';


$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

/*
    name: $exchange
    type: topic
    passive: false //don't check if an exchange with the same name exists
    durable: false // the exchange will survive server restarts(Metadata of a durable queue is stored on disk, Durable queues will be recovered on node boot, including messages in them published as persistent)
    auto_delete: false //the exchange won't be deleted once the channel is closed.
*/
$channel->exchange_declare($exchange, AMQPExchangeType::HEADERS, false, false, true);

/*
    name: $queue
    passive: false //don't check if an queue with the same name exists
    durable: true // the queue will survive server restarts
                  //(if true contine from stored progress else start the begining)
                 //(Metadata of a durable queue is stored on disk, Durable queues will be recovered on node boot, including messages in them published as persistent)
    exclusive: false // the queue can be accessed in other channels
    auto_delete: false //the queue won't be deleted once the channel is closed.
*/
$channel->queue_declare($manager_queue, false, false, false, true);
$channel->queue_declare($teacher_queue, false, false, false, true);
$channel->queue_declare($student_queue, false, false, false, true);

$channel->queue_bind($manager_queue, $exchange);
$channel->queue_bind($teacher_queue, $exchange);
$channel->queue_bind($student_queue, $exchange);

$terminal_message = isset($argv[1]) && !empty($argv[1]) ? $argv[1] : 'Message is empty.';

$msg = new AMQPMessage($terminal_message);

$headers = new Wire\AMQPTable(array(
    'name' => 'mahmoud',
    'date' => new DateTime()
 ));

$msg->set('application_headers', $headers);

$channel->basic_publish($msg, $exchange, $routing_key);

echo ' [x] Sent ', $routing_key, ':', $data, "\n";

$channel->close();
$connection->close();