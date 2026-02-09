<?php

namespace Vatly\API\Resources;

class OrderLineCollection extends BaseResourcePage
{
    public function getCollectionResourceName(): ?string
    {
        return "orderlines";
    }

    protected function createResourceObject(): OrderLine
    {
        return new OrderLine($this->apiClient);
    }

    /**
     * Get a specific order line.
     * Returns null if the order line cannot be found.
     */
    public function get(string $lineId): ?OrderLine
    {
        foreach ($this as $line) {
            if ($line->id === $lineId) {
                return $line;
            }
        }

        return null;
    }
}
