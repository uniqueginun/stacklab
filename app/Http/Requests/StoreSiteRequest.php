<?php

namespace App\Http\Requests;

use App\Enums\ConnectionStatus;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Site::class);
    }

    public function TargetServer(): ?Server
    {
        $serverUuid = $this->string('server_id')->toString();

        return $this->user()->servers()->firstWhere('uuid', $serverUuid);

    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {

        $server = $this->TargetServer();

        return [
            'server_id' => [
                'required',
                'uuid',
                Rule::exists('servers', 'uuid')->where(fn ($query) => $query
                    ->where('user_id', $this->user()?->id)
                    ->where('connection_status', ConnectionStatus::CONNECTED)),
            ],
            'domain' => [
                'required',
                'string',
                'max:253',
                'regex:/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))+$/',
                Rule::unique('sites', 'domain')->where(
                    fn ($query) => $query->where('server_id', $server instanceof Server ? $server->id : 0),
                ),
            ],
            'type' => ['required', Rule::in(['laravel', 'php'])],
            'php_version' => ['required', Rule::in(['8.3', '8.4'])],
            'web_directory' => ['required', 'string', 'max:255', 'regex:/^(?:[A-Za-z0-9._-]+(?:\/[A-Za-z0-9._-]+)*)?$/'],
        ];
    }

    /**
     * @return array{
     *     domain: string,
     *     type: string,
     *     php_version: string,
     *     web_directory: string
     * }
     */
    public function siteAttributes(): array
    {
        $webDirectory = trim($this->string('web_directory')->toString(), '/');

        if ($webDirectory === '') {
            $webDirectory = $this->string('type')->toString() === 'laravel' ? 'public' : '.';
        }

        return [
            'domain' => strtolower($this->string('domain')->toString()),
            'type' => $this->string('type')->toString(),
            'php_version' => $this->string('php_version')->toString(),
            'web_directory' => $webDirectory,
        ];
    }
}
