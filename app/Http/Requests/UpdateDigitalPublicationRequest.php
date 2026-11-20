<?php

namespace App\Http\Requests;

class UpdateDigitalPublicationRequest extends StoreDigitalPublicationRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('publication'));
    }
}
