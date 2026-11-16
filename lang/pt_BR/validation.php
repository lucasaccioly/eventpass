<?php

return [
    'after' => 'O campo :attribute deve ser uma data posterior a :date.',
    'between' => [
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'string' => 'O campo :attribute deve ter entre :min e :max caracteres.',
    ],
    'confirmed' => 'A confirmação de :attribute não confere.',
    'date' => 'O campo :attribute deve ser uma data válida.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'lowercase' => 'O campo :attribute deve estar em minúsculas.',
    'max' => [
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'min' => [
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'Este :attribute já está em uso.',
    'current_password' => 'A senha está incorreta.',

    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'description' => 'descrição',
        'venue' => 'local',
        'starts_at' => 'data e hora',
        'capacity' => 'vagas',
        'code' => 'código',
    ],
];
