<?php
namespace RMS\PushNotificationsBundle\Event;
use RMS\PushNotificationsBundle\Message\MessageInterface;
use Symfony\Component\EventDispatcher\Event;

class FilterNotificationErrorEvent extends Event
{
    /** @var MessageInterface $message */
    protected $message;
    protected $response;
    protected $responseContent;

    /**
    * @param MessageInterface $message
    * @param $response
    * @param string|null $responseContent
    */
    public function __construct($message, $response, $responseContent = null)
    {
        $this->message = $message;
        $this->response = $response;
        $this->responseContent = $responseContent;
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

    /**
    * @return string|null
    */
    public function getResponseContent()
    {
      return $this->responseContent;
    }
}
