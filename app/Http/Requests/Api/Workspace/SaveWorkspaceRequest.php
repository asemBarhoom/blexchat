<?php

namespace App\Http\Requests\Api\Workspace;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Adding a workspace, and renaming one: the business's name.
 */
class SaveWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace === null || $workspace->owner_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }
}
