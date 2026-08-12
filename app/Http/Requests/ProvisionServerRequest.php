<?php

namespace App\Http\Requests;

use App\Models\Server;
use App\Support\PhpVersions;
use App\Support\ProvisioningProfiles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProvisionServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Server $server */
        $server = $this->route('server');

        abort_unless($server->user_id === $this->user()?->id, 404);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'profile' => ['required', 'string', Rule::in(app(ProvisioningProfiles::class)->keys())],
            'php_version' => ['nullable', 'string', Rule::in(PhpVersions::all())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $profile = $this->string('profile')->toString();

            if ($profile === '' || $validator->errors()->isNotEmpty()) {
                return;
            }

            $profiles = app(ProvisioningProfiles::class);

            if ($profiles->requiresPhp($profile) && blank($this->input('php_version'))) {
                $validator->errors()->add('php_version', 'Select a PHP version for this profile.');
            }
        });
    }
}
