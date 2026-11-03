<?php

namespace App\Http\Requests\Api\Automation;

use App\Models\Automation;
use App\Models\AutomationTemplate;
use Illuminate\Validation\Rule;

class StoreWizardAutomationRequest extends WizardAutomationRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'template' => ['nullable', 'string', Rule::exists('automation_templates', 'key')],
        ];
    }

    /**
     * The template the wizard started from, when it named one.
     */
    public function template(): ?AutomationTemplate
    {
        return $this->filled('template') ? AutomationTemplate::where('key', $this->input('template'))->first() : null;
    }

    protected function current(): ?Automation
    {
        return null;
    }
}
