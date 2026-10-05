#!/usr/bin/env bash
set -u
targets=(
  routes/auth.php
  app/Http/Controllers/Auth
  app/Http/Controllers/ProfileController.php
  app/Http/Requests/Auth
  app/Http/Requests/ProfileUpdateRequest.php
  app/View/Components/AppLayout.php
  app/View/Components/GuestLayout.php
  resources/views/auth
  resources/views/profile
  resources/views/layouts
  resources/views/components
  resources/views/dashboard.blade.php
  tests/Feature/Auth
  tests/Feature/ProfileTest.php
)
for t in "${targets[@]}"; do
  if [ -e "$t" ]; then rm -r "$t" && echo "removed  $t"; else echo "absent   $t"; fi
done

echo
echo "Livewire/Volt leftovers (delete only if present and you didn't build on them):"
for t in app/Livewire resources/views/livewire app/Providers/VoltServiceProvider.php; do
  [ -e "$t" ] && echo "found    $t"
done