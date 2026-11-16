<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'reason' => $this->reason,
            'amount' => $this->amount / 100,
            'balance_after' => $this->balance_after / 100,
            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}
