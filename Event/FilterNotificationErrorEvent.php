<?php
namespace RMS\PushNotificationsBundle\Event;
use RMS\PushNotificationsBundle\Message\MessageInterface;
use Symfony\Component\EventDispatcher\Event;

class FilterNotificationErrorEvent extends Event
{
    /** @var MessageInterface $message */
    protected $message;
    protected $response;

    /**
    * @param MessageInterface $message
    * @param $response
    */
    public function __construct($message, $response)
    {
        $this->message = $message;
        $this->response = $response;
    }

    /**
    * @return MessageInterface
    */
    public function getMessage()
    {
      return $this->message;
    }

    /**
    * @return string|null
    */
    public function getResponse()
    {
      return is_null($this->response) ? null : json_encode($this->response);
    }


}
