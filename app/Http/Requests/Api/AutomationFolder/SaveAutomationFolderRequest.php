<?php

namespace App\Http\Requests\Api\AutomationFolder;

use App\Models\AutomationFolder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create folder, and renaming one: a name the owner has not used for another folder.
 */
class SaveAutomationFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $folder = $this->route('folder');

        return $folder === null || $this->user()->workspace()->holds($folder);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('automation_folders', 'name')->where('workspace_id', $this->user()->workspace()->id)->ignore($this->route('folder')),
            ],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->route('folder') === null && $this->user()->workspace()->automationFolders()->count() >= AutomationFolder::MAX_PER_USER) {
                $validator->errors()->add('name', 'You have reached '.AutomationFolder::MAX_PER_USER.' folders. Delete one to add another.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }
}
