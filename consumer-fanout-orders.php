<?php

require(__DIR__.'/vendor/autoload.php');

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

$exchange = 'restaurant';
$queue = 'orders';

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
    name: $queue    // should be unique in fanout exchange.
    passive: false //don't check if an queue with the same name exists
    durable: true // the queue will survive server restarts
                  //(if true contine from stored progress else start the begining)
                 //(Metadata of a durable queue is stored on disk, Durable queues will be recovered on node boot, including messages in them published as persistent)
    exclusive: false // the queue might be accessed by other channels
    auto_delete: true //the queue will be deleted once the channel is closed.
*/
$channel->queue_declare($queue, false, false, false, true);

$channel->queue_bind($queue, $exchange);

/**
 * @param \PhpAmqpLib\Message\AMQPMessage $message
 */
function process_message($message)
{
    echo "\n--------\n";
    echo $message->body;
    echo "\n--------\n";

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
    nowait: don't wait for a server response. In case of error the server will raise a channel
            exception
    callback: A PHP Callback
*/

$channel->basic_consume($queue, '', false, false, false, false, 'process_message');

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