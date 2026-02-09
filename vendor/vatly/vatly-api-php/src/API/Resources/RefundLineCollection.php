<?php

namespace Vatly\API\Resources;

class RefundLineCollection extends BaseResourcePage
{
    public function getCollectionResourceName(): ?string
    {
        return "refundlines";
    }

    protected function createResourceObject(): RefundLine
    {
        return new RefundLine($this->apiClient);
    }

    /**
     * Get a specific refund line.
     * Returns null if the refund line cannot be found.
     */
    public function get(string $lineId): ?RefundLine
    {
        foreach ($this as $line) {
            if ($line->id === $lineId) {
                return $line;
            }
        }

        return null;
    }
}
