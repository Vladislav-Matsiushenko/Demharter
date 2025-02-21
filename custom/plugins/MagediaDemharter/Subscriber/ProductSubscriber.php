<?php

namespace MagediaDemharter\Subscriber;

use Enlight\Event\SubscriberInterface;
use Shopware\Models\Article\Article;
use Doctrine\ORM\EntityManager;

class ProductSubscriber implements SubscriberInterface
{
    const MANUFACTURER_DESCRIPTION = '<DIV><FONT size=2 face=Tahoma></FONT>&nbsp;</DIV><DIV><FONT size=2 face=Tahoma>Informationen zur Produktsicherheit:</FONT></DIV><DIV><FONT size=2 face=Tahoma></FONT>&nbsp;</DIV><DIV><FONT size=2 face=Tahoma>Hersteller/EU-Verantwortlicher:</FONT></DIV><DIV><FONT size=2 face=Tahoma>Quad Stadel Schwab GmbH</FONT></DIV><DIV><FONT size=2 face=Tahoma>Im Herrmannshof 5</FONT></DIV><DIV><FONT size=2 face=Tahoma>91595 Burgoberbach</FONT></DIV><DIV><FONT size=2 face=Tahoma>Telefon: +0049 09805/932550</FONT></DIV>';

// Local
    private $isActiveFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/isActiveProductSubscriber.txt';

// Staging
//    private $isActiveFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/isActiveProductSubscriber.txt';

// Live
//    private $isActiveFilePath = '/usr/home/mipzhm/public_html/files/demharter/isActiveProductSubscriber.txt';

    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Shopware\Models\Article\Article::postPersist' => 'onProductCreate'
        ];
    }

    public function onProductCreate(\Enlight_Event_EventArgs $args)
    {
        $isActive = file_get_contents($this->isActiveFilePath);
        if (!$isActive) {
            return;
        }

        $entity = $args->getEntity();

        if ($entity instanceof Article) {
            $descriptionLong = $entity->getDescriptionLong();
            $entity->setDescriptionLong($descriptionLong . self::MANUFACTURER_DESCRIPTION);

            $this->entityManager->persist($entity);
            $this->entityManager->flush();
        }
    }

    public function setIsActive(bool $isActive)
    {
        file_put_contents($this->isActiveFilePath, $isActive);
    }
}
