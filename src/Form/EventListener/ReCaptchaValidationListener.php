<?php

namespace App\Form\EventListener;

use ReCaptcha\ReCaptcha;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Request;

class ReCaptchaValidationListener implements EventSubscriberInterface
{
    private $reCaptcha;

    public function __construct(ReCaptcha $reCaptcha)
    {
        $this->reCaptcha = $reCaptcha;
    }

    public static function getSubscribedEvents()
    {
        return [
            FormEvents::POST_SUBMIT => 'onPostSubmit'
        ];
    }

    public function onPostSubmit(FormEvent $event)
    {
        $request = Request::createFromGlobals();

        $result = $this->reCaptcha
            ->setExpectedHostname($request->getHost())
            ->verify($request->request->get('g-recaptcha-response'), $request->getClientIp());

        if (!$result->isSuccess()) {
            $form = $event->getForm();
            $form->addError(new FormError('The captcha is invalid. Please try again.'));
        }
    }
}