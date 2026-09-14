<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personal Finance — Income &amp; Expense Manager</title>
    @vite(['resources/css/app.css'])
    <script defer src="https://unpkg.com/alpinejs@3.14.8/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-100 min-h-screen text-slate-900">
<div x-data="financeApp()" x-init="init()" class="max-w-5xl mx-auto p-4 sm:p-6">

    <header class="flex items-center justify-between py-4">
        <h1 class="text-2xl font-bold">💰 Personal Finance</h1>
        <div x-show="token" class="flex items-center gap-3">
            <span class="text-sm text-slate-600" x-text="user?.name"></span>
            <button @click="logout()" class="px-3 py-1.5 text-sm bg-slate-900 text-white rounded hover:bg-slate-700">Logout</button>
        </div>
    </header>

    <div x-show="message" x-text="message" class="mb-4 p-3 rounded bg-blue-100 text-blue-900 text-sm"></div>

    <!-- GUEST VIEWS -->
    <template x-if="!token">
        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white rounded shadow p-5">
                <div class="flex gap-2 mb-4">
                    <button @click="authTab='login'" :class="authTab==='login' ? 'bg-slate-900 text-white' : 'bg-slate-200'" class="px-3 py-1.5 rounded text-sm">Login</button>
                    <button @click="authTab='register'" :class="authTab==='register' ? 'bg-slate-900 text-white' : 'bg-slate-200'" class="px-3 py-1.5 rounded text-sm">Register</button>
                </div>

                <!-- LOGIN -->
                <form x-show="authTab==='login'" @submit.prevent="login()" class="space-y-3">
                    <h2 class="font-semibold">Login (verified emails only)</h2>
                    <input x-model="loginForm.email" type="email" required placeholder="Email" class="w-full border rounded px-3 py-2">
                    <p x-show="errors.email" x-text="errors.email?.[0]" class="text-red-600 text-sm"></p>
                    <input x-model="loginForm.password" type="password" required placeholder="Password" class="w-full border rounded px-3 py-2">
                    <button class="w-full bg-green-600 text-white rounded py-2 hover:bg-green-500">Login</button>
                </form>

                <!-- REGISTER -->
                <form x-show="authTab==='register'" @submit.prevent="register()" class="space-y-3">
                    <h2 class="font-semibold">Create account</h2>
                    <input x-model="registerForm.name" required placeholder="Name" class="w-full border rounded px-3 py-2">
                    <p x-show="errors.name" x-text="errors.name?.[0]" class="text-red-600 text-sm"></p>
                    <input x-model="registerForm.email" type="email" required placeholder="Email" class="w-full border rounded px-3 py-2">
                    <p x-show="errors.email" x-text="errors.email?.[0]" class="text-red-600 text-sm"></p>
                    <input x-model="registerForm.password" type="password" required placeholder="Password (min 8)" class="w-full border rounded px-3 py-2">
                    <input x-model="registerForm.password_confirmation" type="password" required placeholder="Confirm password" class="w-full border rounded px-3 py-2">
                    <p x-show="errors.password" x-text="errors.password?.[0]" class="text-red-600 text-sm"></p>
                    <button class="w-full bg-slate-900 text-white rounded py-2 hover:bg-slate-700">Register</button>
                    <p class="text-xs text-slate-500">We send a verification email. You can log in only after verifying.</p>
                </form>
            </div>

            <div class="bg-white rounded shadow p-5">
                <h2 class="font-semibold mb-2">Didn't get the email?</h2>
                <form @submit.prevent="resendVerification()" class="space-y-3">
                    <input x-model="resendEmail" type="email" required placeholder="Your email" class="w-full border rounded px-3 py-2">
                    <button class="w-full bg-amber-500 text-white rounded py-2 hover:bg-amber-400">Resend verification email</button>
                </form>
                <div class="mt-4 text-sm text-slate-600">
                    <p class="font-semibold">How it works</p>
                    <ol class="list-decimal ml-5 space-y-1">
                        <li>Register via <code>POST /api/register</code></li>
                        <li>Click the link in the email (<code>GET /api/email/verify/{id}/{hash}</code>)</li>
                        <li>Login via <code>POST /api/login</code> to get a Sanctum bearer token</li>
                        <li>AlpineJS calls the API with <code>fetch()</code> + <code>Authorization: Bearer</code></li>
                    </ol>
                </div>
            </div>
        </div>
    </template>

    <!-- AUTHENTICATED VIEWS -->
    <template x-if="token">
        <div class="space-y-4">
            <!-- DASHBOARD -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-white rounded shadow p-4"><p class="text-xs text-slate-500">Income</p><p class="text-xl font-bold text-green-600" x-text="dashboard.total_income ?? '—'"></p></div>
                <div class="bg-white rounded shadow p-4"><p class="text-xs text-slate-500">Expenses</p><p class="text-xl font-bold text-red-600" x-text="dashboard.total_expense ?? '—'"></p></div>
                <div class="bg-white rounded shadow p-4"><p class="text-xs text-slate-500">Balance</p><p class="text-xl font-bold" x-text="dashboard.balance ?? '—'"></p></div>
                <div class="bg-white rounded shadow p-4"><p class="text-xs text-slate-500">Transactions</p><p class="text-xl font-bold" x-text="dashboard.transaction_count ?? '—'"></p></div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <!-- TRANSACTIONS -->
                <div class="bg-white rounded shadow p-5">
                    <h2 class="font-semibold mb-3">Transactions</h2>
                    <div class="flex flex-wrap gap-2 mb-3 text-sm">
                        <select x-model="filters.type" @change="loadTransactions(1)" class="border rounded px-2 py-1">
                            <option value="">All types</option>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                        <input x-model="filters.category" @input.debounce.500ms="loadTransactions(1)" placeholder="Filter category" class="border rounded px-2 py-1 w-32">
                        <input x-model="filters.from" type="date" @change="loadTransactions(1)" class="border rounded px-2 py-1">
                        <input x-model="filters.to" type="date" @change="loadTransactions(1)" class="border rounded px-2 py-1">
                    </div>

                    <form @submit.prevent="saveTransaction()" class="grid grid-cols-2 gap-2 mb-4 text-sm">
                        <select x-model="txForm.type" required class="border rounded px-2 py-1.5 col-span-1">
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                        <input x-model="txForm.category" list="catlist" required placeholder="Category" class="border rounded px-2 py-1.5">
                        <datalist id="catlist"><template x-for="c in categories" :key="c.id"><option :value="c.name"></option></template></datalist>
                        <input x-model="txForm.amount" type="number" step="0.01" min="0.01" required placeholder="Amount" class="border rounded px-2 py-1.5">
                        <input x-model="txForm.transaction_date" type="date" required class="border rounded px-2 py-1.5">
                        <input x-model="txForm.description" placeholder="Note (optional)" class="border rounded px-2 py-1.5 col-span-2">
                        <div class="col-span-2 flex gap-2">
                            <button class="flex-1 bg-green-600 text-white rounded py-1.5" x-text="editingTxId ? 'Update' : 'Add'"></button>
                            <button type="button" x-show="editingTxId" @click="resetTxForm()" class="px-3 bg-slate-200 rounded">Cancel</button>
                        </div>
                        <p x-show="errors.amount" x-text="errors.amount?.[0]" class="text-red-600 text-xs col-span-2"></p>
                    </form>

                    <ul class="divide-y text-sm">
                        <template x-for="t in transactions" :key="t.id">
                            <li class="py-2 flex justify-between items-center">
                                <div>
                                    <span :class="t.type==='income' ? 'text-green-700' : 'text-red-700'" class="font-semibold" x-text="t.type"></span>
                                    <span class="ml-2" x-text="t.category"></span>
                                    <span class="ml-2 text-slate-500" x-text="t.transaction_date"></span>
                                    <div class="text-slate-500 text-xs" x-text="t.description"></div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono" x-text="t.amount"></span>
                                    <button @click="editTransaction(t)" class="text-blue-600 text-xs">Edit</button>
                                    <button @click="deleteTransaction(t.id)" class="text-red-600 text-xs">Del</button>
                                </div>
                            </li>
                        </template>
                    </ul>
                    <div class="flex justify-between mt-3 text-sm">
                        <button @click="loadTransactions(pagination.current_page - 1)" :disabled="!pagination.prev" class="px-2 py-1 bg-slate-200 rounded disabled:opacity-50">Prev</button>
                        <span x-text="`Page ${pagination.current_page ?? 1} / ${pagination.last_page ?? 1}`"></span>
                        <button @click="loadTransactions(pagination.current_page + 1)" :disabled="!pagination.next" class="px-2 py-1 bg-slate-200 rounded disabled:opacity-50">Next</button>
                    </div>
                </div>

                <!-- CATEGORIES + BY CATEGORY -->
                <div class="space-y-4">
                    <div class="bg-white rounded shadow p-5">
                        <h2 class="font-semibold mb-3">Categories</h2>
                        <form @submit.prevent="saveCategory()" class="flex gap-2 text-sm mb-3">
                            <input x-model="catForm.name" required placeholder="Name" class="border rounded px-2 py-1.5 flex-1">
                            <select x-model="catForm.type" class="border rounded px-2 py-1.5">
                                <option value="">Any</option>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                            <button class="bg-slate-900 text-white rounded px-3" x-text="editingCatId ? 'Save' : 'Add'"></button>
                        </form>
                        <ul class="text-sm divide-y">
                            <template x-for="c in categories" :key="c.id">
                                <li class="py-1.5 flex justify-between">
                                    <span><span x-text="c.name" class="font-medium"></span> <span class="text-slate-400" x-text="c.type ?? ''"></span></span>
                                    <span>
                                        <button @click="editCategory(c)" class="text-blue-600 text-xs mr-2">Edit</button>
                                        <button @click="deleteCategory(c.id)" class="text-red-600 text-xs">Del</button>
                                    </span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div class="bg-white rounded shadow p-5">
                        <h2 class="font-semibold mb-3">Totals by category</h2>
                        <ul class="text-sm divide-y">
                            <template x-for="row in dashboard.by_category ?? []" :key="row.category + row.type">
                                <li class="py-1 flex justify-between"><span><span x-text="row.category"></span> (<span x-text="row.type"></span>)</span><span class="font-mono" x-text="row.total"></span></li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function financeApp() {
    return {
        token: null, user: null, authTab: 'login',
        message: '', errors: {},
        loginForm: { email: '', password: '' },
        registerForm: { name: '', email: '', password: '', password_confirmation: '' },
        resendEmail: '',
        dashboard: {}, transactions: [], categories: [],
        pagination: { current_page: 1, last_page: 1, prev: null, next: null },
        filters: { type: '', category: '', from: '', to: '' },
        txForm: { type: 'expense', category: '', amount: '', description: '', transaction_date: new Date().toISOString().slice(0, 10) },
        editingTxId: null,
        catForm: { name: '', type: '' },
        editingCatId: null,

        init() {
            this.token = localStorage.getItem('pf_token');
            const u = localStorage.getItem('pf_user');
            this.user = u ? JSON.parse(u) : null;
            if (this.token) this.loadAll();
        },
        headers(json = true) {
            const h = { 'Accept': 'application/json' };
            if (json) h['Content-Type'] = 'application/json';
            if (this.token) h['Authorization'] = 'Bearer ' + this.token;
            return h;
        },
        async handle(res) {
            const data = await res.json().catch(() => ({}));
            if (res.status === 401) { this.logout(true); throw new Error(data.message || 'Unauthenticated.'); }
            return { res, data };
        },
        showErrors(data) { this.errors = data.errors ?? {}; this.message = data.message ?? ''; },
        clearMsgs() { this.message = ''; this.errors = {}; },

        async register() {
            this.clearMsgs();
            const { res, data } = await this.handle(await fetch('/api/register', { method: 'POST', headers: this.headers(), body: JSON.stringify(this.registerForm) }));
            if (res.status === 201) { this.message = data.message; this.authTab = 'login'; this.registerForm = { name: '', email: '', password: '', password_confirmation: '' }; }
            else this.showErrors(data);
        },
        async login() {
            this.clearMsgs();
            const { res, data } = await this.handle(await fetch('/api/login', { method: 'POST', headers: this.headers(), body: JSON.stringify(this.loginForm) }));
            if (res.ok) {
                this.token = data.token; this.user = data.user;
                localStorage.setItem('pf_token', data.token); localStorage.setItem('pf_user', JSON.stringify(data.user));
                this.message = data.message; this.loadAll();
            } else this.showErrors(data);
        },
        async logout(silent = false) {
            try { if (this.token) await fetch('/api/logout', { method: 'POST', headers: this.headers() }); } catch (e) {}
            this.token = null; this.user = null;
            localStorage.removeItem('pf_token'); localStorage.removeItem('pf_user');
            if (!silent) this.message = 'Logged out.';
        },
        async resendVerification() {
            this.clearMsgs();
            const { data } = await this.handle(await fetch('/api/email/verification-notification', { method: 'POST', headers: this.headers(), body: JSON.stringify({ email: this.resendEmail }) }));
            this.message = data.message;
        },
        async loadAll() { await Promise.all([this.loadDashboard(), this.loadTransactions(1), this.loadCategories()]); },
        async loadDashboard() {
            const { res, data } = await this.handle(await fetch('/api/dashboard', { headers: this.headers(false) }));
            if (res.ok) this.dashboard = data.data;
        },
        async loadTransactions(page = 1) {
            const q = new URLSearchParams({ page, ...Object.fromEntries(Object.entries(this.filters).filter(([, v]) => v)) });
            const { res, data } = await this.handle(await fetch('/api/transactions?' + q, { headers: this.headers(false) }));
            if (res.ok) {
                const p = data.data;
                this.transactions = p.data ?? [];
                this.pagination = { current_page: p.current_page, last_page: p.last_page, prev: p.prev_page_url, next: p.next_page_url };
            }
        },
        resetTxForm() {
            this.editingTxId = null;
            this.txForm = { type: 'expense', category: '', amount: '', description: '', transaction_date: new Date().toISOString().slice(0, 10) };
        },
        editTransaction(t) { this.editingTxId = t.id; this.txForm = { type: t.type, category: t.category, amount: t.amount, description: t.description ?? '', transaction_date: (t.transaction_date || '').slice(0, 10) }; },
        async saveTransaction() {
            this.clearMsgs();
            const url = this.editingTxId ? `/api/transactions/${this.editingTxId}` : '/api/transactions';
            const method = this.editingTxId ? 'PUT' : 'POST';
            const { res, data } = await this.handle(await fetch(url, { method, headers: this.headers(), body: JSON.stringify(this.txForm) }));
            if (res.status === 201 || res.ok) { this.message = data.message; this.resetTxForm(); this.loadTransactions(this.pagination.current_page); this.loadDashboard(); }
            else this.showErrors(data);
        },
        async deleteTransaction(id) {
            if (!confirm('Delete this transaction?')) return;
            const { data } = await this.handle(await fetch(`/api/transactions/${id}`, { method: 'DELETE', headers: this.headers(false) }));
            this.message = data.message; this.loadTransactions(this.pagination.current_page); this.loadDashboard();
        },
        async loadCategories() {
            const { res, data } = await this.handle(await fetch('/api/categories', { headers: this.headers(false) }));
            if (res.ok) this.categories = data.data;
        },
        editCategory(c) { this.editingCatId = c.id; this.catForm = { name: c.name, type: c.type ?? '' }; },
        async saveCategory() {
            this.clearMsgs();
            const payload = { name: this.catForm.name, type: this.catForm.type || null };
            const url = this.editingCatId ? `/api/categories/${this.editingCatId}` : '/api/categories';
            const method = this.editingCatId ? 'PUT' : 'POST';
            const { res, data } = await this.handle(await fetch(url, { method, headers: this.headers(), body: JSON.stringify(payload) }));
            if (res.status === 201 || res.ok) { this.message = data.message; this.editingCatId = null; this.catForm = { name: '', type: '' }; this.loadCategories(); }
            else this.showErrors(data);
        },
        async deleteCategory(id) {
            if (!confirm('Delete this category?')) return;
            const { data } = await this.handle(await fetch(`/api/categories/${id}`, { method: 'DELETE', headers: this.headers(false) }));
            this.message = data.message; this.loadCategories();
        },
    };
}
</script>
</body>
</html>
