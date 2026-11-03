<?php

namespace App\Http\Requests\Api\Automation;

use App\Models\Automation;

class UpdateWizardAutomationRequest extends WizardAutomationRequest
{
    /**
     * Only the owner edits an automation; anyone else gets a 403 before validation.
     */
    public function authorize(): bool
    {
        return $this->current()->user_id === $this->user()->id;
    }

    /**
     * The kind and channel are fixed once an automation exists: whatever was sent, the saved ones count.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['kind' => $this->current()->kind(), 'channel_id' => $this->current()->channel_id]);
    }

    protected function current(): Automation
    {
        return $this->route('automation');
    }
}
