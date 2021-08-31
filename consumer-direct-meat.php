<?php

require(__DIR__.'/vendor/autoload.php');

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

$exchange = 'food';
$meat_queue = 'meat';
$meat_routing_key = 'meat';
$consumerTag = '';

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

$channel->queue_bind($meat_queue, $exchange, $meat_routing_key);

/**
 * @param \PhpAmqpLib\Message\AMQPMessage $message
 */
function process_message($message)
{
    
    $message_body = json_decode($message->body);
    writeDataToFile($message_body);

    $message->ack();

    // Send a message with the string "quit" to cancel the consumer.
    if ($message->body === 'quit') {
        $message->getChannel()->basic_cancel($message->getConsumerTag());
    }
}

/*
    queue: Queue from where to get the messages
    consumer_tag: Consumer identifier
    no_local: Don't receive messages published by this consumer.
    no_ack: If set to true, automatic acknowledgement mode will be used by this consumer. See https://www.rabbitmq.com/confirms.html for details.
    exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
    nowait:
    callback: A PHP Callback
*/

$channel->basic_consume($meat_queue, $consumerTag, false, false, false, false, 'process_message');

/**
 * @param \PhpAmqpLib\Channel\AMQPChannel $channel
 * @param \PhpAmqpLib\Connection\AbstractConnection $connection
 */
function shutdown($channel, $connection)
{
    $channel->close();
    $connection->close();
}

register_shutdown_function('shutdown', $channel, $connection);

// Loop as long as the channel has callbacks registered
while ($channel->is_consuming()) {
    $channel->wait();
}

function writeDataToFile($message)
{
    echo $message->name . "\n";

    $folder_location = 'uploads/';
    $file_path = 'uploads/' . strtolower(trim(str_replace(' ', '_', 'meat_'.$message->name)));

    //check path
    if (!file_exists($folder_location)) {
        mkdir($folder_location, 0777, true);
    }

    //check file to open
    $file_object = fopen($file_path, 'a+');

    //write data to file
    fwrite($file_object, $message->name . "\n" .$message->email. "\n" .$message->phone);

    fclose($file_object);
}