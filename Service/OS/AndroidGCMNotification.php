<?php

namespace RMS\PushNotificationsBundle\Service\OS;

use RMS\PushNotificationsBundle\Exception\InvalidMessageTypeException,
    RMS\PushNotificationsBundle\Message\AndroidMessage,
    RMS\PushNotificationsBundle\Message\MessageInterface;
use Buzz\Browser,
    Buzz\Client\AbstractCurl,
    Buzz\Client\Curl,
    Buzz\Client\MultiCurl;
use Symfony\Component\EventDispatcher\EventDispatcher;
use RMS\PushNotificationsBundle\Event\FilterNotificationErrorEvent;
use RMS\PushNotificationsBundle\Events;

class AndroidGCMNotification implements OSNotificationServiceInterface
{
    /**
     * GCM endpoint
     *
     * @var string
     */
    protected $apiURL = "https://android.googleapis.com/gcm/send";

    /**
     * Google GCM API key
     *
     * @var string
     */
    protected $apiKey;

    /**
     * Max registration count
     *
     * @var integer
     */
    protected $registrationIdMaxCount = 1000;

    /**
     * Browser object
     *
     * @var \Buzz\Browser
     */
    protected $browser;

    /**
     * Collection of the responses from the GCM communication
     *
     * @var array
     */
    protected $responses;

    /**
     * Symfony2 EventDispatcher
     *
     * @var EventDispatcher EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * Constructor
     *
     * @param $apiKey
     * @param bool         $useMultiCurl
     * @param EventDispatcher $eventDispatcher
     * @param AbstractCurl $client       (optional)
     */
    public function __construct($apiKey, $useMultiCurl, $eventDispatcher, AbstractCurl $client = null)
    {
        $this->apiKey = $apiKey;
        if (!$client) {
            $client = ($useMultiCurl ? new MultiCurl() : new Curl());
        }
        $this->browser = new Browser($client);
        $this->browser->getClient()->setVerifyPeer(false);
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Sends the data to the given registration IDs via the GCM server
     *
     * @param  \RMS\PushNotificationsBundle\Message\MessageInterface              $message
     * @throws \RMS\PushNotificationsBundle\Exception\InvalidMessageTypeException
     * @return bool
     */
    public function send(MessageInterface $message)
    {
        if (!$message instanceof AndroidMessage) {
            throw new InvalidMessageTypeException(sprintf("Message type '%s' not supported by GCM", get_class($message)));
        }
        if (!$message->isGCM()) {
            throw new InvalidMessageTypeException("Non-GCM messages not supported by the Android GCM sender");
        }

        $headers = array(
            "Authorization: key=" . $this->apiKey,
            "Content-Type: application/json",
        );
        $data = array_merge(
            $message->getGCMOptions(),
            array("data" => $message->getData())
        );

        // Chunk number of registration IDs according to the maximum allowed by GCM
        $chunks = array_chunk($message->getGCMIdentifiers(), $this->registrationIdMaxCount);

        // Perform the calls (in parallel)
        $this->responses = array();
        foreach ($chunks as $registrationIDs) {
            $data["registration_ids"] = $registrationIDs;
            $this->responses[] = $this->browser->post($this->apiURL, $headers, json_encode($data));
        }

        // If we're using multiple concurrent connections via MultiCurl
        // then we should flush all requests
        if ($this->browser->getClient() instanceof MultiCurl) {
            $this->browser->getClient()->flush();
        }

        // Determine success
        foreach ($this->responses as $response) {
            $messageResponse = json_decode($response->getContent());
            if ($messageResponse === null || $messageResponse->success == 0 || $messageResponse->failure > 0) {
                $event = new FilterNotificationErrorEvent($message, $messageResponse, $response->getContent());
                $this->eventDispatcher->dispatch(Events::NOTIFICATION_ERROR, $event);
                return false;
            }
        }

        return true;
    }

    /**
     * Returns responses
     *
     * @return array
     */
    public function getResponses()
    {
        return $this->responses;
    }
}
