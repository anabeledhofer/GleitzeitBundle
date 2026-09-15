<?php

namespace KimaiPlugin\GleitzeitBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * baut das "Gleitzeit"-menü mit unterpunkten:
 *   Gleitzeit
 *   ├─ Zeitliste
 *   ├─ Diäten
 *   └─ Verwaltung   (nur admins)
 */
final class MenuSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMainMenuEvent::class => ['onMenuConfigure', 100],
        ];
    }

    public function onMenuConfigure(ConfigureMainMenuEvent $event): void
    {
        // eltern-eintrag - die route zeigt auf die zeitliste, damit ein
        // klick auf den hauptpunkt direkt dorthin führt
        $parent = new MenuItemModel('gleitzeit', 'Gleitzeit', 'gleitzeit_zeitliste', [], 'far fa-clock');

        // unterpunkte
        $parent->addChild(
            new MenuItemModel('gleitzeit_zeitliste', 'Zeitliste', 'gleitzeit_zeitliste', [], 'far fa-clock')
        );
        $parent->addChild(
            new MenuItemModel('gleitzeit_diaeten', 'Diäten', 'gleitzeit_diaeten', [], 'fas fa-euro-sign')
        );

        // verwaltung nur für admins
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $parent->addChild(
                new MenuItemModel('gleitzeit_admin', 'Verwaltung', 'gleitzeit_admin', [], 'fas fa-users-cog')
            );
        }

        // erst NACH dem anhängen der kinder ins hauptmenü setzen
        $event->getMenu()->addChild($parent);
    }
}