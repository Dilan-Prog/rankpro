<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="name">Nombre</label>
        <input class="input" type="text" name="name" id="name" value="{{ old('name') }}" required>
        @error('name')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="email">Email</label>
        <input class="input" type="email" name="email" id="email" value="{{ old('email') }}" required>
        @error('email')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="password">Contraseña</label>
        <input class="input" type="password" name="password" id="password" required>
        @error('password')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="password_confirmation">Confirmar contraseña</label>
        <input class="input" type="password" name="password_confirmation" id="password_confirmation" required>
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="role_id">Rol</label>
        <select class="select" name="role_id" id="role_id">
            <option value="">Sin rol</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->label }}</option>
            @endforeach
        </select>
        @error('role_id')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field" style="display:flex; align-items:flex-end;">
        <label class="checkbox-item">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
            Cuenta activa
        </label>
    </div>
</div>
