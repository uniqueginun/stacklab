<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachSiteRepositoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('site')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'repository' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
            'branch' => ['required', 'string', 'max:255', Rule::notIn([''])],
        ];
    }

    /**
     * @return array{repository: string, branch: string}
     */
    public function repositoryAttributes(): array
    {
        return [
            'repository' => $this->string('repository')->toString(),
            'branch' => $this->string('branch')->toString(),
        ];
    }
}
