<?php

namespace App\Http\Requests;

use App\Services\SsrfGuard;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url', 'max:2048'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                app(SsrfGuard::class)->assertSafeUrl((string) $this->input('base_url'));
            } catch (\Throwable $exception) {
                $validator->errors()->add('base_url', $exception->getMessage());
            }
        });
    }
}
