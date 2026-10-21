<?php
namespace App\Libraries\Core\Events;

/** Only deployed code defines executable consumers. No payload-selected class/URL. */
final class ConsumerRegistry
{
    private array $entries;

    public function __construct(?array $entries = null)
    {
        $this->entries = $entries ?? [
            'sale.confirmed'=>[
                'sales.perceptions'=>['version'=>1,'handler'=>[LocalAutomation::class,'perceptions']],
                'core.event_evidence'=>['version'=>1,'handler'=>[LocalAutomation::class,'evidence']],
            ],
            'purchase.payment_made'=>[
                'purchases.withholdings'=>['version'=>1,'handler'=>[LocalAutomation::class,'withholdings']],
                'core.event_evidence'=>['version'=>1,'handler'=>[LocalAutomation::class,'evidence']],
            ],
            'sale.cancelled'=>['core.event_evidence'=>['version'=>1,'handler'=>[LocalAutomation::class,'evidence']]],
            'sale.payment_received'=>['core.event_evidence'=>['version'=>1,'handler'=>[LocalAutomation::class,'evidence']]],
            'inventory.stock_received'=>['core.event_evidence'=>['version'=>1,'handler'=>[LocalAutomation::class,'evidence']]],
        ];
    }

    public function forEvent(string $event): array { return $this->entries[$event] ?? []; }

    public function handler(string $event, string $consumer, int $version): ?callable
    {
        $entry = $this->entries[$event][$consumer] ?? null;
        return $entry && $entry['version'] === $version && is_callable($entry['handler']) ? $entry['handler'] : null;
    }
}
