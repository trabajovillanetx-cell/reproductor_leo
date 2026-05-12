@if (config('session.driver') === 'database')
    <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <div class="max-w-xl">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Dispositivos conectados
            </h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Hasta <strong class="text-gray-900 dark:text-gray-200">{{ $maxConcurrentSessions }}</strong> sesiones activas a la vez (misma cuenta). Las que no tengan actividad reciente dejan de contarse solas.
            </p>

            @if ($activeSessions->isEmpty())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No hay otras sesiones recientes en base de datos.</p>
            @else
                <ul class="mt-4 divide-y divide-gray-200 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700">
                    @foreach ($activeSessions as $row)
                        @php
                            $isCurrent = $row->id === $currentSessionId;
                            $when = \Illuminate\Support\Carbon::createFromTimestamp((int) $row->last_activity)->timezone(config('app.timezone'))->diffForHumans();
                        @endphp
                        <li class="flex flex-col gap-2 px-3 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-900 dark:text-gray-100">
                                    @if ($isCurrent)
                                        <span class="text-emerald-600 dark:text-emerald-400">Este dispositivo</span>
                                    @else
                                        <span class="text-gray-700 dark:text-gray-300">Otro dispositivo</span>
                                    @endif
                                    <span class="ml-2 text-xs font-normal text-gray-500 dark:text-gray-400">Actividad {{ $when }}</span>
                                </p>
                                <p class="mt-0.5 truncate font-mono text-xs text-gray-500 dark:text-gray-400" title="{{ $row->user_agent }}">{{ $row->user_agent ?: '—' }}</p>
                                <p class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $row->ip_address ?: '—' }}</p>
                            </div>
                            @if (! $isCurrent)
                                <form
                                    method="POST"
                                    action="{{ route('profile.sessions.destroy', ['session' => $row->id]) }}"
                                    class="shrink-0"
                                    onsubmit="return confirm('¿Cerrar sesión en ese dispositivo?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                        Cerrar sesión
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
