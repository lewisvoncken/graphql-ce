<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Sales\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;

class OrderCreateTest extends WebapiAbstract
{
    const RESOURCE_PATH = '/V1/orders';

    const SERVICE_READ_NAME = 'salesOrderRepositoryV1';

    const SERVICE_VERSION = 'V1';

    const ORDER_INCREMENT_ID = '100000001';

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    protected function setUp()
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
    }

    protected function prepareOrder()
    {
        /** @var \Magento\Sales\Model\Order $orderBuilder */
        $orderFactory = $this->objectManager->get('Magento\Sales\Model\OrderFactory');
        /** @var \Magento\Sales\Api\Data\OrderItemFactory $orderItemFactory */
        $orderItemFactory = $this->objectManager->get('Magento\Sales\Model\Order\ItemFactory');
        /** @var \Magento\Sales\Api\Data\OrderPaymentFactory $orderPaymentFactory */
        $orderPaymentFactory = $this->objectManager->get('Magento\Sales\Model\Order\PaymentFactory');
        /** @var \Magento\Sales\Api\Data\OrderAddressFactory $orderAddressFactory */
        $orderAddressFactory = $this->objectManager->get('Magento\Sales\Model\Order\AddressFactory');

        $extensionAttributes = [
            'gift_message' => [
                'sender' => 'testSender',
                'recipient' => 'testRecipient',
                'message' => 'testMessage'
            ]
        ];
        $order = $orderFactory->create(
            ['data' => $this->getDataStructure('Magento\Sales\Api\Data\OrderInterface', $extensionAttributes)]
        );
        $orderItem = $orderItemFactory->create(
            ['data' => $this->getDataStructure('Magento\Sales\Api\Data\OrderItemInterface', $extensionAttributes)]
        );
        $orderPayment = $orderPaymentFactory->create(
            ['data' => $this->getDataStructure('Magento\Sales\Api\Data\OrderPaymentInterface')]
        );
        $orderAddressBilling = $orderAddressFactory->create(
            ['data' => $this->getDataStructure('Magento\Sales\Api\Data\OrderAddressInterface')]
        );

        $email = uniqid() . 'email@example.com';
        $orderItem->setSku('sku#1');
        if (TESTS_WEB_API_ADAPTER == self::ADAPTER_SOAP) {
            $orderItem->setData('parent_item', $orderItem->getData() + ['parent_item' => null]);
            $orderItem->setAdditionalData('test');
        } else {
            $orderItem->setData('parent_item', ['weight' => 1]);
        }
        $orderPayment->setCcLast4('4444');
        $orderPayment->setMethod('checkmo');
        $orderPayment->setAccountStatus('ok');
        $orderPayment->setAdditionalInformation([]);
        $order->setCustomerEmail($email);
        $order->setBaseGrandTotal(100);
        $order->setGrandTotal(100);
        $order->setItems([$orderItem->getData()]);
        $order->setPayments([$orderPayment->getData()]);
        $orderAddressBilling->setCity('City');
        $orderAddressBilling->setPostcode('12345');
        $orderAddressBilling->setLastname('Last Name');
        $orderAddressBilling->setFirstname('First Name');
        $orderAddressBilling->setTelephone('+00(000)-123-45-57');
        $orderAddressBilling->setStreet(['Street']);
        $orderAddressBilling->setCountryId(1);
        $orderAddressBilling->setAddressType('billing');

        $orderAddressShipping = $orderAddressFactory->create(
            ['data' => $this->getDataStructure('Magento\Sales\Api\Data\OrderAddressInterface')]
        );
        $orderAddressShipping->setCity('City');
        $orderAddressShipping->setPostcode('12345');
        $orderAddressShipping->setLastname('Last Name');
        $orderAddressShipping->setFirstname('First Name');
        $orderAddressShipping->setTelephone('+00(000)-123-45-57');
        $orderAddressShipping->setStreet(['Street']);
        $orderAddressShipping->setCountryId(1);
        $orderAddressShipping->setAddressType('shipping');

        $orderData = $order->getData();
        $orderData['billing_address'] = $orderAddressBilling->getData();
        $orderData['billing_address']['street'] = ['Street'];
        $orderData['shipping_address'] = $orderAddressShipping->getData();
        $orderData['shipping_address']['street'] = ['Street'];
        return $orderData;
    }

    protected function getDataStructure($className, array $extensionAttributes = null)
    {
        $refClass = new \ReflectionClass($className);
        $constants = $refClass->getConstants();
        $data = array_fill_keys($constants, null);
        unset($data['custom_attributes']);
        $data['extension_attributes'] = $extensionAttributes;
        return $data;
    }

    public function testOrderCreate()
    {
        $order = $this->prepareOrder();

        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'save',
            ],
        ];
        /** @var array $webApiCallOrder */
        $webApiCallOrder = $this->_webApiCall($serviceInfo, ['entity' => $order]);
        $this->assertNotEmpty($webApiCallOrder);
        $this->assertTrue((bool)$webApiCallOrder['entity_id']);

        /** @var \Magento\Sales\Api\Data\Order\Repository $repository */
        $repository = $this->objectManager->get('Magento\Sales\Api\Data\Order\Repository');
        /** @var \Magento\Sales\Api\Data\OrderInterface $actualOrder */
        $actualOrder = $repository->get($webApiCallOrder['entity_id']);
        /** @var \Magento\GiftMessage\Api\Data\MessageInterface $orderGiftMessage */
        $orderGiftMessage = $actualOrder->getExtensionAttributes()->getGiftMessage();
        /** @var \Magento\Sales\Api\Data\OrderItemInterface $actualItemOrder */
        $actualOrderItem = $actualOrder->getItems();
        $actualOrderItem = array_pop($actualOrderItem);
        /** @var \Magento\GiftMessage\Api\Data\MessageInterface $orderItemGiftMessage */
        $orderItemGiftMessage = $actualOrderItem->getExtensionAttributes()->getGiftMessage();

        $this->assertEquals($order['base_grand_total'], $actualOrder->getBaseGrandTotal());
        $this->assertEquals($order['grand_total'], $actualOrder->getGrandTotal());

        $expectedOrderGiftMessage = $order['extension_attributes']['gift_message'];
        $this->assertEquals($expectedOrderGiftMessage['message'],$orderGiftMessage->getMessage());
        $this->assertEquals($expectedOrderGiftMessage['sender'],$orderGiftMessage->getSender());
        $this->assertEquals($expectedOrderGiftMessage['recipient'],$orderGiftMessage->getRecipient());

        $expectedOrderItemGiftMessage = $order['items'][0]['extension_attributes']['gift_message'];
        $this->assertEquals($expectedOrderItemGiftMessage['message'],$orderItemGiftMessage->getMessage());
        $this->assertEquals($expectedOrderItemGiftMessage['sender'],$orderItemGiftMessage->getSender());
        $this->assertEquals($expectedOrderItemGiftMessage['recipient'],$orderItemGiftMessage->getRecipient());
    }
}
