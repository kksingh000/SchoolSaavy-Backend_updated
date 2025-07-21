<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'monthly_price' => $this->monthly_price,
            'yearly_price' => $this->yearly_price,
            'features' => $this->features,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'subscription' => $this->when($this->pivot, [
                'activated_at' => $this->pivot?->activated_at,
                'expires_at' => $this->pivot?->expires_at,
                'status' => $this->pivot?->status,
                'settings' => $this->pivot?->settings ? json_decode($this->pivot->settings, true) : null,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
