<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Git Functions') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div id="flash-message" class="mt-6"></div>
                    <!-- Git operations form -->
                    <form id="git-form" class="mt-6 space-y-6">
                        @csrf
                        <div>
                            <x-input-label for="repository_id" :value="__('Repository')" />
                            <select id="repository_id" name="repository_id" class="mt-1 block w-full text-black" required>
                                <option value="">Select a Repository</option>
                            </select>
                        </div>

                        <!-- Tabs -->
                        <div class="mt-6">
                            <ul class="flex border-b">
                                <li class="mr-1">
                                    <button id="branches-tab" type="button" class="tab-button py-2 px-4 text-gray-700 border-b-2 border-transparent hover:border-gray-400" onclick="showTab('branches')">{{ __('List Branches') }}</button>
                                </li>
                                <li class="mr-1">
                                    <button id="checkout-tab" type="button" class="tab-button py-2 px-4 text-gray-700 border-b-2 border-transparent hover:border-gray-400" onclick="showTab('checkout')">{{ __('Checkout Branch') }}</button>
                                </li>
                                <li class="mr-1">
                                    <button id="commands-tab" type="button" class="tab-button py-2 px-4 text-gray-700 border-b-2 border-transparent hover:border-gray-400" onclick="showTab('commands')">{{ __('Run Commands') }}</button>
                                </li>
                            </ul>
                        </div>

                        <!-- Tab content -->
                        <div id="branches-section" class="tab-content mt-6 hidden">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    {{ __('Branches') }}
                                </h3>
                                <div class="flex items-center gap-4 mt-4">
                                    <x-primary-button type="button" onclick="listBranches()">{{ __('List Branches') }}</x-primary-button>
                                </div>
                                <ul id="branches-list" class="mt-4 space-y-2">
                                    <!-- Branches will be loaded here by JavaScript -->
                                </ul>
                            </div>
                        </div>

                        <div id="checkout-section" class="tab-content mt-6 hidden">
                            <div>
                                <x-input-label for="branch_name" :value="__('Branch Name')" />
                                <x-text-input id="branch_name" name="branch_name" type="text" class="mt-1 block w-full" />
                            </div>

                            <div class="flex items-center gap-4 mt-4">
                                <x-primary-button type="button" onclick="checkoutBranch()">{{ __('Checkout Branch') }}</x-primary-button>
                            </div>
                        </div>

                        <div id="commands-section" class="tab-content mt-6 hidden">
                            <div>
                                <x-input-label for="commands" :value="__('Commands')" />
                                <textarea id="commands" name="commands" rows="5" class="mt-1 block w-full text-black" placeholder='Commands Needs Seperate by ","'></textarea>
                            </div>

                            <div class="flex items-center gap-4 mt-4">
                                <x-primary-button type="button" onclick="runCommands()">{{ __('Run Commands') }}</x-primary-button>
                            </div>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            fetchRepositories();

            // Handle repository selection change
            document.getElementById('repository_id').addEventListener('change', () => {
                const repositoryId = document.getElementById('repository_id').value;
                if (repositoryId) {
                    showTab('branches'); // Show branches tab by default when repository is selected
                }
            });

            // Show initial tab
            showTab('branches');
        });

        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            document.getElementById(`${tabName}-section`).classList.remove('hidden');

            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-gray-400');
                button.classList.add('border-transparent');
            });
            document.getElementById(`${tabName}-tab`).classList.add('border-gray-400');
        }

        async function fetchRepositories() {
            try {
                const response = await fetch('{{ route('repositories.index') }}');
                const repositories = await response.json();

                const repoSelect = document.getElementById('repository_id');
                repoSelect.innerHTML = `<option value="">Select a Repository</option>`;
                repositories.forEach(repo => {
                    const option = document.createElement('option');
                    option.value = repo.id;
                    option.textContent = repo.name;
                    repoSelect.appendChild(option);
                });
            } catch (error) {
                console.error('Error:', error);
            }
        }

        async function listBranches() {
            const repositoryId = document.getElementById('repository_id').value;

            try {
                const response = await fetch(`{{ url('/api/repositories') }}/${repositoryId}/git/branches`);
                const branches = await response.json();

                const list = document.getElementById('branches-list');
                list.innerHTML = branches.map(branch => `
                    <li class="border p-4 rounded">
                        <p class="text-gray-600 dark:text-gray-400">${branch}</p>
                    </li>
                `).join('');
            } catch (error) {
                console.error('Error:', error);
            }
        }

        async function checkoutBranch() {
            const repositoryId = document.getElementById('repository_id').value;
            const branchName = document.getElementById('branch_name').value;

            try {
                const response = await fetch(`{{ url('/api/repositories') }}/${repositoryId}/git/checkout`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({ branch_name: branchName }),
                });

                const result = await response.json();

                if (response.ok) {
                    showMessage('Branch checked out successfully', 'success');
                } else {
                    showMessage(result.message || 'Error checking out branch', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        async function runCommands() {
            const repositoryId = document.getElementById('repository_id').value;
            const commandsInput  = document.getElementById('commands').value.trim();
            let commands;
            try {
                commands = commandsInput.split(',').map(command => command.trim()).filter(command => command.length > 0);

                const response = await fetch(`{{ url('/api/repositories') }}/${repositoryId}/git/run-commands`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({ commands }),
                });

                const result = await response.json();

                if (response.ok) {
                    showMessage('Commands executed successfully', 'success');
                } else {
                    showMessage(result.message || 'Error executing commands', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function showMessage(message, type) {
            const flashMessage = document.getElementById('flash-message');
            flashMessage.innerHTML = `
                <div class="${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white p-4 rounded mb-4">
                    ${message}
                </div>
            `;
        }
    </script>
</x-app-layout>
