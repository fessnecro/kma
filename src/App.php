<?php

namespace Application;

use ErrorException;
use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

class App
{
    private \PDO $db;
    private \PDO $clickhouse;
    private AMQPStreamConnection $amqpConnection;

    /**
     * Construct
     */
    public function __construct()
    {
        try {
            //mariadb
            $dbHost = getenv('DB_HOST');
            $dbName = getenv('DB_NAME');
            $dbUsername = getenv('DB_USERNAME');
            $dbPassword = getenv('DB_PASSWORD');
            $dbCharset = getenv('DB_CHARSET');

            $this->db = new \PDO("mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}",
                $dbUsername,
                $dbPassword
            );

            //rabbit
            $rabbitHost = getenv('RABBIT_HOST');
            $rabbitPort = getenv('RABBIT_PORT');
            $rabbitUser = getenv('RABBIT_USER');
            $rabbitPassword = getenv('RABBIT_PASSWORD');
            $this->amqpConnection = new AMQPStreamConnection($rabbitHost, $rabbitPort, $rabbitUser, $rabbitPassword);

            //clickhouse
            $clickhouseHost = getenv('CH_HOST');
            $clickhouseUser = getenv('CH_USERNAME');
            $clickhousePassword = getenv('CH_PASSWORD');
            $clickhouseName= getenv('CH_NAME');
            $this->clickhouse = new \PDO("mysql:host={$clickhouseHost};dbname={$clickhouseName};port=9004",
                $clickhouseUser,
                $clickhousePassword
            );
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }

    }

    /**
     * @param int $count
     * @param int $minDelay
     * @param int $maxDelay
     * @return void
     * @throws Exception
     */
    public function generateUrls(int $count = 10, int $minDelay = 5, int $maxDelay = 30): void
    {
        $exchange = 'kma_exchange';
        $queue = 'kma_queue';

        $channel = $this->amqpConnection->channel();

        $urls = [];

        for ($i = 0; $i < $count; $i++)
        {
            $urls[] = 'https://'
                . substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, rand(5,10))
                . '.ru';

        }

        $channel->queue_declare($queue, false, true, false, false);
        $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
        $channel->queue_bind($queue, $exchange);

        foreach ($urls as $url)
        {
            sleep(rand($minDelay, $maxDelay));

            $message = new AMQPMessage($url, ['content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
            $channel->basic_publish($message, $exchange);
        }

        $channel->close();
        $this->amqpConnection->close();
    }

    /**
     * @return array|false
     */
    public function getStat()
    {
       return $this->db
           ->query('SELECT COUNT(*) as cnt, MINUTE(FROM_UNIXTIME(`created_at`)) as minute, AVG(length) as avg_content from url GROUP BY minute')
           ->fetchAll();
    }

    /**
     * @return array|false
     */
    public function getStatCH()
    {
        return $this->clickhouse
            ->query('SELECT COUNT(*) as cnt, MINUTE(FROM_UNIXTIME(`created_at`)) as minute, AVG(length) as avg_content from url GROUP BY minute')
            ->fetchAll();
    }

    /**
     * @throws ErrorException
     * @throws Exception
     */
    public function consume()
    {
        $exchange = 'kma_exchange';
        $queue = 'kma_queue';
        $consumerTag = 'kma_consumer';

        $channel = $this->amqpConnection->channel();


        $channel->queue_declare($queue, false, true, false, false);
        $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
        $channel->queue_bind($queue, $exchange);


        $channel->basic_consume($queue, $consumerTag, false, false, false, false, function (AMQPMessage $message) {
            echo "\n--------\n";
            echo $message->body;
            echo "\n--------\n";

            $message->ack();

            $data = [
                'url' => $message->body,
                'length' => mb_strlen($message->body),
                'created_at' => time()
            ];

            $sql = "INSERT INTO url (`url`, `created_at`, `length`) VALUES (:url, :created_at, :length)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($data);

            // Send a message with the string "quit" to cancel the consumer.
            if ($message->body === 'quit') {
                $message->getChannel()->basic_cancel($message->getConsumerTag());
            }
        });

        register_shutdown_function(function (AMQPChannel $channel, AbstractConnection $connection) {
            $channel->close();
            $connection->close();
        }, $channel, $this->amqpConnection);

        $channel->consume();
    }
}