<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <h1 class="text-xl font-semibold text-gray-900">Trading Platform</h1>
          </div>
          <div class="flex items-center space-x-4">
            <span class="text-sm text-gray-700">Welcome, {{ authStore.user?.name }}</span>
            <button
              @click="handleLogout"
              class="text-sm text-gray-500 hover:text-gray-700"
            >
              Logout
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left Column - Balance & Assets -->
          <div class="lg:col-span-1 space-y-6">
            <!-- Balance Card -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
              <div class="p-5">
                <div class="flex items-center">
                  <div class="shrink-0">
                    <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                    </svg>
                  </div>
                  <div class="ml-5 w-0 flex-1">
                    <dl>
                      <dt class="text-sm font-medium text-gray-500 truncate">USD Balance</dt>
                      <dd class="text-lg font-medium text-gray-900">
                        ${{ formatNumber(profile?.balance || 0) }}
                      </dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>

            <!-- Assets -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
              <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Assets</h3>
                <div class="mt-5 space-y-3">
                  <div v-for="asset in profile?.assets" :key="asset.symbol" class="flex justify-between items-center">
                    <div>
                      <p class="text-sm font-medium text-gray-900">{{ asset.symbol }}</p>
                      <p class="text-sm text-gray-500">Available</p>
                    </div>
                    <div class="text-right">
                      <p class="text-sm font-medium text-gray-900">{{ formatNumber(asset.amount) }}</p>
                      <p class="text-sm text-gray-500">Locked: {{ formatNumber(asset.locked_amount) }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Middle Column - Order Form -->
          <div class="lg:col-span-1">
            <div class="bg-white shadow rounded-lg">
              <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Place Order</h3>
                <form @submit.prevent="placeOrder">
                  <div class="space-y-4">
                    <!-- Symbol -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Symbol</label>
                      <select v-model="orderForm.symbol" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="BTC-USD">BTC-USD</option>
                        <option value="ETH-USD">ETH-USD</option>
                      </select>
                    </div>

                    <!-- Side -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Side</label>
                      <div class="mt-1 grid grid-cols-2 gap-2">
                        <button
                          type="button"
                          @click="orderForm.side = 'buy'"
                          :class="[
                            'py-2 px-4 rounded-md text-sm font-medium',
                            orderForm.side === 'buy'
                              ? 'bg-green-600 text-white'
                              : 'bg-gray-200 text-gray-900 hover:bg-gray-300'
                          ]"
                        >
                          Buy
                        </button>
                        <button
                          type="button"
                          @click="orderForm.side = 'sell'"
                          :class="[
                            'py-2 px-4 rounded-md text-sm font-medium',
                            orderForm.side === 'sell'
                              ? 'bg-red-600 text-white'
                              : 'bg-gray-200 text-gray-900 hover:bg-gray-300'
                          ]"
                        >
                          Sell
                        </button>
                      </div>
                    </div>

                    <!-- Price -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Price (USD)</label>
                      <input
                        v-model.number="orderForm.price"
                        type="number"
                        step="0.01"
                        min="0"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        placeholder="0.00"
                      />
                    </div>

                    <!-- Amount -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Amount</label>
                      <input
                        v-model.number="orderForm.amount"
                        type="number"
                        step="0.00000001"
                        min="0"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        placeholder="0.00"
                      />
                    </div>

                    <!-- Total -->
                    <div class="pt-2 border-t">
                      <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Total</span>
                        <span class="font-medium">${{ formatNumber(orderTotal) }}</span>
                      </div>
                    </div>

                    <!-- Error -->
                    <div v-if="orderError" class="text-red-500 text-sm">
                      {{ orderError }}
                    </div>

                    <!-- Submit Button -->
                    <button
                      type="submit"
                      :disabled="placingOrder"
                      class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                    >
                      {{ placingOrder ? 'Placing Order...' : 'Place Order' }}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Right Column - Orders -->
          <div class="lg:col-span-1">
            <div class="bg-white shadow rounded-lg">
              <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-lg leading-6 font-medium text-gray-900">Your Orders</h3>
                  <select v-model="selectedSymbol" @change="fetchOrders" class="text-sm border-gray-300 rounded">
                    <option value="BTC-USD">BTC-USD</option>
                    <option value="ETH-USD">ETH-USD</option>
                  </select>
                </div>
                <div class="space-y-3">
                  <div v-if="orders.length === 0" class="text-center text-gray-500 py-4">
                    No orders found
                  </div>
                  <div v-for="order in orders" :key="order.id" class="border rounded-lg p-3">
                    <div class="flex justify-between items-start">
                      <div>
                        <span :class="[
                          'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                          order.side === 'buy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        ]">
                          {{ order.side.toUpperCase() }}
                        </span>
                        <p class="mt-1 text-sm text-gray-900">
                          {{ formatNumber(order.amount) }} @ ${{ formatNumber(order.price) }}
                        </p>
                      </div>
                      <div class="text-right">
                        <span :class="[
                          'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                          getStatusClass(order.status)
                        ]">
                          {{ getStatusText(order.status) }}
                        </span>
                        <button
                          v-if="order.status === 0"
                          @click="cancelOrder(order.id)"
                          :disabled="cancellingOrder === order.id"
                          class="mt-2 text-xs text-red-600 hover:text-red-800 disabled:opacity-50"
                        >
                          {{ cancellingOrder === order.id ? 'Cancelling...' : 'Cancel' }}
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

const router = useRouter();
const authStore = useAuthStore();

const profile = ref(null);
const orders = ref([]);
const selectedSymbol = ref('BTC-USD');
const placingOrder = ref(false);
const cancellingOrder = ref(null);
const orderError = ref('');

const orderForm = ref({
  symbol: 'BTC-USD',
  side: 'buy',
  price: '',
  amount: '',
});

const orderTotal = computed(() => {
  if (!orderForm.value.price || !orderForm.value.amount) return 0;
  return orderForm.value.price * orderForm.value.amount;
});

const formatNumber = (num) => {
  if (num === null || num === undefined) return '0.00';
  return parseFloat(num).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 8,
  });
};

const getStatusClass = (status) => {
  switch (status) {
    case 0: return 'bg-yellow-100 text-yellow-800';
    case 1: return 'bg-green-100 text-green-800';
    case 2: return 'bg-gray-100 text-gray-800';
    default: return 'bg-gray-100 text-gray-800';
  }
};

const getStatusText = (status) => {
  switch (status) {
    case 0: return 'Open';
    case 1: return 'Filled';
    case 2: return 'Cancelled';
    default: return 'Unknown';
  }
};

const fetchProfile = async () => {
  try {
    const response = await axios.get('/api/profile');
    profile.value = response.data;
  } catch (error) {
    console.error('Failed to fetch profile:', error);
  }
};

const fetchOrders = async () => {
  try {
    const response = await axios.get(`/api/orders?symbol=${selectedSymbol.value}`);
    orders.value = response.data;
  } catch (error) {
    console.error('Failed to fetch orders:', error);
  }
};

const placeOrder = async () => {
  placingOrder.value = true;
  orderError.value = '';

  try {
    await axios.post('/api/orders', orderForm.value);
    
    // Reset form
    orderForm.value = {
      symbol: selectedSymbol.value,
      side: 'buy',
      price: '',
      amount: '',
    };

    // Refresh data
    await fetchProfile();
    await fetchOrders();
  } catch (error) {
    orderError.value = error.response.data.errors?.order?.[0] || 'Failed to place order';
  } finally {
    placingOrder.value = false;
  }
};

const cancelOrder = async (orderId) => {
  cancellingOrder.value = orderId;

  try {
    await axios.post(`/api/orders/${orderId}/cancel`);
    await fetchProfile();
    await fetchOrders();
  } catch (error) {
    console.error('Failed to cancel order:', error);
  } finally {
    cancellingOrder.value = null;
  }
};

const handleLogout = async () => {
  await authStore.logout();
  router.push('/login');
};

const setupPusher = () => {
  if (!authStore.user || !window.pusher) return;

  const channel = window.pusher.subscribe(`private-user.${authStore.user.id}`);
  
  channel.bind('order.matched', (data) => {
    // Refresh orders when an order is matched
    fetchOrders();
    fetchProfile();
  });
};

onMounted(async () => {
  await authStore.initAuth();
  if (!authStore.isAuthenticated) {
    router.push('/login');
    return;
  }
  
  setupPusher();
  fetchProfile();
  fetchOrders();
});

onUnmounted(() => {
  if (authStore.user) {
    window.pusher.unsubscribe(`private-user.${authStore.user.id}`);
  }
});
</script>
