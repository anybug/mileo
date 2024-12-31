<?php

namespace App\Controller;

use App\Controller\App\DashboardAppController;
use App\Controller\App\OrderAppCrudController;
use App\Controller\App\UserAppCrudController;
use App\Entity\Invoice;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Payum\Stripe\Request\Api\CreatePlan;
use Payum\Core\Payum;
use Payum\Core\Request\GetHumanStatus;

use App\Entity\Payment;
use App\Entity\Purchase;
use App\Entity\User;
use App\Entity\Plan;
use App\Entity\Subscription;
use DateInterval;
use DateTime;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class PaymentController extends AbstractController 
{
    /**
     * @Route("/prepare-payment/{order_id}/checkout", name="payum_prepare_payment")
     */
    public function prepareAction(Payum $payum, int $order_id, EntityManagerInterface $manager)
    {
        $order = $manager->getRepository(Order::class)->find($order_id);
        $user = $this->getUser();
        $gatewayName = 'stripe_checkout_session';
        $storage = $payum->getStorage(Payment::class);
        $payment = $storage->create();
        $payment->setNumber(uniqid());
        $payment->setCurrencyCode('EUR');
        $payment->setTotalAmount($order->getPlan()->getTotalCost()*(120/100)*100);
        $payment->setDescription($order->getPlan()->getPlanDescription());
        $payment->setClientId($user->getId());
        $payment->setClientEmail($user->getEmail());

        $storage->update($payment);

        $captureToken = $payum->getTokenFactory()->createCaptureToken(
            $gatewayName, 
            $payment, 
            'payum_payment_done', // the route to redirect after capture
            ['order_id' => $order_id],
        );
            
        return $this->redirect($captureToken->getTargetUrl());    
    }

    /**
     * @Route("/payment-done/{order_id}", name="payum_payment_done")
     */
    public function done(AdminUrlGenerator $adminUrlGenerator,Request $request, Payum $payum, EntityManagerInterface $manager, int $order_id)
    {
        $token = $payum->getHttpRequestVerifier()->verify($request);
        $gateway = $payum->getGateway($token->getGatewayName());

        // You can invalidate the token, so that the URL cannot be requested any more:
        // $payum->getHttpRequestVerifier()->invalidate($token);

        // Once you have the token, you can get the payment entity from the storage directly. 
        //$identity = $token->getDetails();
        //$payment = $payum->getStorage($identity->getClass())->find($identity);
        // Or Payum can fetch the entity for you while executing a request (preferred).
        $gateway->execute($status = new GetHumanStatus($token));
        $payment = $status->getFirstModel();
        if ($payment->getDetails()["status"] == "succeeded") {

            $user = $this->getUser();
            $subscription = $this->getUser()->getSubscription();
            $order = $manager->getRepository(Order::class)->find($order_id);
            $plan = $order->getPlan();
            $subscription->setPlan($plan);
            
            $invoice = new Invoice;

            $manager->persist($invoice);
            $manager->flush();
            
            // order in bd
            $order->setUser($user);
            $order->setPlan($plan);
            $order->setCreatedAt(new \DateTime());
            $order->setUpdatedAt(new \DateTime());
            $order->setProductName($plan->getName());
            $order->setProductDescription($plan->getPlanDescription());
            $order->setTotalHt($plan->getTotalCost());
            $order->calculateVatAmount();
            $order->setInvoice($invoice);
            $order->setStatus("paid");
            
            // subscription in bd 
            
            if( $subscription == null){
                $subscription = new Subscription;
                $subscription->setSubscriptionStart(new \DateTime('now'));
                $subscription->setSubscriptionEnd(new \DateTime('+'.$plan->getPlanPeriod().' month'));
            } else if ($subscription->getSubscriptionEnd() < new \DateTime('now')){
                $subscription->setSubscriptionEnd(new \DateTime('+'.$plan->getPlanPeriod().' month'));
            } else {
                $date = new DateTime($subscription->getSubscriptionEnd()->format('y-m-d'));
                $date->modify('+'.$plan->getPlanPeriod().' month');
                $subscription->setSubscriptionEnd($date);
            }
            $order->setSubscriptionEnd($subscription->getSubscriptionEnd());
            $manager->persist($order);
            $manager->persist($subscription);
            $user->setSubscription($subscription);

            //user in bd
            $manager->persist($user);
            $manager->flush();

            $url = $adminUrlGenerator
            ->setDashboard(DashboardAppController::class)
            ->setController(OrderAppCrudController::class)
            ->setAction('successPayment')
            ->set('id',$order->getId())
            ->generateUrl();

            return $this->redirect($url);

            //return $this->redirectToRoute('payum_payment_success',['id' => $order->getId()]);
        }

        $url = $adminUrlGenerator
            ->setDashboard(DashboardAppController::class)
            ->setController(UserAppCrudController::class)
            ->setAction('index')
            
            ->generateUrl();

        return $this->redirect($url);
    }
}



