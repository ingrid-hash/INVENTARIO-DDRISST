<?php

namespace App\Http\Requests\Inventario;

use App\Models\Bien;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validacion de un bien, tanto al crearlo como al editarlo.
 *
 * La regla importante es condicional: un bien capturado como ADICION debe traer
 * cuenta y forma de adquisicion, y si fue donado, el programa que lo financio.
 * Los bienes que vienen de una importacion historica no tienen esa exigencia,
 * porque en los archivos originales ese dato casi nunca esta anotado.
 */
class BienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->bien()
            ? $this->user()->can('bienes.editar')
            : $this->user()->can('bienes.crear');
    }

    private function bien(): ?Bien
    {
        $bien = $this->route('bien');

        return $bien instanceof Bien ? $bien : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo' => [
                'required',
                'string',
                'max:40',
                // Conviven los dos formatos oficiales —el hexadecimal viejo de 8
                // caracteres y el nuevo con anio y unidad— mas el provisional
                // que genera el sistema para los bienes que llegaron sin codigo.
                'regex:/^([0-9A-Fa-f]{8}|\d{4}-\d{3}-[A-Za-z]{2,4}-\d{3,4}|SC-[0-9A-Z\-]+-\d{3,4})$/',
                Rule::unique('bienes', 'codigo')->ignore($this->bien()?->id),
            ],
            'descripcion' => ['required', 'string', 'max:2000'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'precio_unitario' => ['required', 'numeric', 'min:0', 'max:99999999.99'],

            'unidad_servicio_id' => ['required', 'integer', Rule::exists('unidades_servicio', 'id')],
            'renglon_id' => ['nullable', 'integer', Rule::exists('renglones', 'id')],

            'tipo_movimiento' => ['required', Rule::in(array_keys(Bien::MOVIMIENTOS))],
            'forma_adquisicion' => ['nullable', Rule::in(array_keys(Bien::FORMAS_ADQUISICION))],
            'programa' => ['nullable', 'string', 'max:255'],
            'documento_respaldo' => ['nullable', 'string', 'max:255'],

            'fecha_ingreso' => ['nullable', 'date', 'before_or_equal:today'],
            'anio_ingreso' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],

            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('tipo_movimiento') !== 'adicion') {
                return;
            }

            if (! $this->filled('renglon_id')) {
                $validator->errors()->add('renglon_id', 'Toda adición debe indicar a qué cuenta pertenece.');
            }

            if (! $this->filled('forma_adquisicion')) {
                $validator->errors()->add('forma_adquisicion', 'Indique si la adición fue por compra o por donación.');
            }

            if ($this->input('forma_adquisicion') === 'donacion' && ! $this->filled('programa')) {
                $validator->errors()->add('programa', 'Si el bien fue donado, indique el programa que lo financió.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'codigo' => 'código de inventario',
            'descripcion' => 'descripción',
            'precio_unitario' => 'precio unitario',
            'unidad_servicio_id' => 'unidad de servicio',
            'renglon_id' => 'cuenta',
            'tipo_movimiento' => 'tipo de movimiento',
            'forma_adquisicion' => 'forma de adquisición',
            'fecha_ingreso' => 'fecha de ingreso',
            'anio_ingreso' => 'año de ingreso',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.regex' => 'El código debe ser de 8 caracteres hexadecimales (0033C31E), del formato nuevo (2026-211-CHI-0013) o un provisional del sistema (SC-211-CHI-0001).',
        ];
    }
}
