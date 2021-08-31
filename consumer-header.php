<?php
require(__DIR__.'/vendor/autoload.php');

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
$exchange = 'topic_logs';
$school_queue = 'school';
$manager_queue = 'manager';
$teahcer_queue = 'teacher';
$student_queue = 'student';

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, false, true);

/*
    name: $queue
    passive: false //don't check if an queue with the same name exists
    durable: true // the queue will survive server restarts
                  //(if true contine from stored progress else start the begining)
                 //(Metadata of a durable queue is stored on disk, Durable queues will be recovered on node boot, including messages in them published as persistent)
    exclusive: false // the queue can be accessed in other channels
    auto_delete: false //the queue won't be deleted once the channel is closed.
*/
$channel->queue_declare($school_queue, false, false, false, true);
$channel->queue_declare($manager_queue, false, false, false, true);
$channel->queue_declare($teahcer_queue, false, false, false, true);
$channel->queue_declare($student_queue, false, false, false, true);

$channel->queue_bind($school_queue, $exchange);
$channel->queue_bind($manager_queue, $exchange);
$channel->queue_bind($teahcer_queue, $exchange);
$channel->queue_bind($student_queue, $exchange);

$binding_keys = array_slice($argv, 1);
if (empty($binding_keys)) {
    file_put_contents('php://stderr', "Usage: $argv[0] [binding_key]\n");
    exit(1);
}

echo " [*] Waiting for logs. To exit press CTRL+C\n";

$callback = function ($msg) {
    $props = $msg->get_properties();
    print_r($props);

    echo ' [x] ', $msg->delivery_info['routing_key'], ':', $msg->body, "\n";
};

foreach ($binding_keys as $binding_key) {
    if($binding_key=='school.manager'){
        $channel->basic_consume($manager_queue, '', false, true, false, false, $callback);
    }elseif($binding_key=='school.teacher'){
        $channel->basic_consume($teahcer_queue, '', false, true, false, false, $callback);
    }elseif($binding_key=='school.student'){
        $channel->basic_consume($student_queue, '', false, true, false, false, $callback);
    }else{
        $channel->basic_consume($school_queue, '', false, true, false, false, $callback);
    }
}

while ($channel->is_open()) {
    $channel->wait();
}

$channel->close();
$connection->close();