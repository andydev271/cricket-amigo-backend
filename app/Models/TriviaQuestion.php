<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['question', 'options', 'answer_index', 'difficulty'])]
class TriviaQuestion extends Model
{
    protected function casts(): array
    {
        return [
            'question' => 'array',
            'options' => 'array',
            'answer_index' => 'integer',
        ];
    }

    /** Localized shape for the client — answer index is never sent. */
    public function localized(string $locale): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question[$locale] ?? $this->question['en'],
            'options' => $this->options[$locale] ?? $this->options['en'],
            'difficulty' => $this->difficulty,
        ];
    }
}
