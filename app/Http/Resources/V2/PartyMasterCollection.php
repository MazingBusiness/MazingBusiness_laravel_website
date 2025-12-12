<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PartyMasterCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {
                return [
                    'id' => $data->id,
                    'party_code' => $data->party_code,
                    'virtual_account_number' => $data->virtual_account_number,
                    'name' => $data->name,
                    'company_name' => $data->company_name,
                    'address' => $data->address ?? "",
                    'city' => $data->city ?? "",
                    'state' => $data->state ?? "",
                    'pincode' => $data->postal_code ?? "",
                    'gstin' => $data->gstin,
                    'phone' => $data->phone ?? "",
                    'email' => $data->email ?? "",
                    'aadhar_card' => $data->aadhar_card,
                    'warehouse' => ['id' => $data->warehouse->id, 'name' => $data->warehouse->name],
                    'manager' => ($data->manager) ? ['id' => $data->manager->id, 'name' => $data->manager->name, 'phone' => $data->manager->phone, 'email' => $data->manager->email] : null,
                    'credit_limit' => $data->credit_limit,
                    'credit_days' => $data->credit_days,
                    'discounts' => [],
                    'billing_companies' => new ShortAddressCollection($data->addresses),
                    'transport' => $data->shipper_allocation,
                    'created_at' => date('Y-m-d H:i:s', strtotime($data->created_at)),
                    'updated_at' => date('Y-m-d H:i:s', strtotime($data->updated_at)),
                ];
            }),
        ];
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200,
        ];
    }
}
