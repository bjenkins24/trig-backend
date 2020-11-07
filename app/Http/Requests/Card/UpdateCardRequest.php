<?php

namespace App\Http\Requests\Card;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateCardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        if ($this->has('url') && ! Str::contains($this->url, 'http')) {
            $this->merge(['url' => "http://{$this->get('url')}"]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id'     => 'required',
            'url'    => 'url',
        ];
    }
}
