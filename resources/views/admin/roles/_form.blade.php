{{-- Shared by create.blade.php and edit.blade.php --}}
@php
    $selectedPermissions = isset($role) ? $role->permissions->pluck('id')->all() : [];
@endphp

<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="label">Nombre del rol</label>
        <input class="input" type="text" name="label" id="label" value="{{ old('label', $role->label ?? '') }}"
            required placeholder="Ej. Gerente de Cuentas">
        @error('label')
            <span class="field__error">{{ $message }}</span>
        @enderror
    </div>

    <div class="field">
        <label class="field__label" for="name">Identificador</label>
        <input class="input" type="text" name="name" id="name" value="{{ old('name', $role->name ?? '') }}"
            required placeholder="ej. gerente_cuentas" style="font-family: var(--font-mono);">
        <span class="field__hint">Minúsculas, números y guion bajo. Se usa internamente, no se muestra a los usuarios.</span>
        @error('name')
            <span class="field__error">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="description">Descripción</label>
    <textarea class="textarea" name="description" id="description" placeholder="Para qué es este rol...">{{ old('description', $role->description ?? '') }}</textarea>
    @error('description')
        <span class="field__error">{{ $message }}</span>
    @enderror
</div>

<div class="field" style="margin-top: var(--space-5);">
    <label class="field__label">Módulos permitidos</label>
    <div class="checkbox-group">
        @foreach ($permisos as $permiso)
            <label class="checkbox-item">
                <input type="checkbox" name="permissions[]" value="{{ $permiso->id }}"
                    @checked(in_array($permiso->id, old('permissions', $selectedPermissions)))>
                {{ $permiso->label }}
            </label>
        @endforeach
    </div>
</div>
