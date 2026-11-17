<template>
  <div class="rounded-2xl border border-status-warning-border bg-status-warning-bg p-5 shadow-sm sm:p-6">
    <!-- Header: Fee & Status Callout -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-status-warning-border pb-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="inline-flex h-2.5 w-2.5 rounded-full bg-status-warning-text animate-pulse" />
          <h2 class="text-base font-bold text-heading">
            Processing Fee Payment Required
          </h2>
        </div>
        <p class="mt-1 text-xs text-body">
          Your request will enter the departmental review queue once payment is confirmed.
        </p>
      </div>

      <div class="text-left sm:text-right">
        <span class="text-xs font-medium uppercase tracking-wider text-muted">
          Amount Due
        </span>
        <div class="text-2xl font-black tracking-tight text-heading">
          {{ formatXAF(amountDue) }}
        </div>
        <div class="mt-0.5 flex items-center gap-1.5 sm:justify-end">
          <TierBadge
            :name="displayTierName"
            :priority="displayPriority"
            :turnaround-days="displayTurnaroundDays"
            :fee-amount="displayFeeAmount"
          />
        </div>
      </div>
    </div>

    <!-- Upgrade Top-Up Notice (if reopening with tier upgrade) -->
    <div
      v-if="request.is_reopened && request.pending_upgrade_tier_id"
      class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-accent-soft-border bg-accent-soft p-3 text-xs text-accent-soft-text"
    >
      <div>
        <span class="font-semibold">Tier Upgrade Top-Up:</span>
        Upgrading to <strong>{{ request.pending_upgrade_tier?.name || 'higher tier' }}</strong>.
        You already paid {{ formatXAF(request.amount_paid) }} previously.
      </div>
      <button
        type="button"
        class="font-semibold underline hover:text-heading"
        :disabled="busy"
        @click="cancelUpgrade"
      >
        Cancel upgrade & keep {{ request.tier_name }}
      </button>
    </div>

    <!-- Tier Switcher (when multiple tiers are available and not locked in upgrade) -->
    <div
      v-if="availableTiers.length > 1 && !request.pending_upgrade_tier_id"
      class="mt-4"
    >
      <button
        type="button"
        class="text-xs font-semibold text-accent-text hover:text-heading underline"
        @click="showTierSwitcher = !showTierSwitcher"
      >
        {{ showTierSwitcher ? 'Close tier options' : 'Change processing speed / tier' }}
      </button>

      <div
        v-if="showTierSwitcher"
        class="mt-3 grid gap-2.5 sm:grid-cols-3"
      >
        <button
          v-for="tier in availableTiers"
          :key="tier.id"
          type="button"
          class="rounded-xl border p-3 text-left transition"
          :class="tier.id === request.request_type_tier_id
            ? 'border-accent-text bg-accent-soft ring-2 ring-accent-soft-border'
            : 'border-subtle bg-surface-card hover:border-strong'"
          :disabled="busy || tier.id === request.request_type_tier_id"
          @click="selectTier(tier)"
        >
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-heading">{{ tier.name }}</span>
            <span
              v-if="tier.id === request.request_type_tier_id"
              class="text-[10px] font-semibold text-status-success-text bg-status-success-bg rounded px-1.5 py-0.5"
            >
              Current
            </span>
          </div>
          <div class="mt-1 text-sm font-black text-heading">
            {{ formatXAF(tier.fee_amount) }}
          </div>
          <div class="mt-0.5 text-[11px] text-muted">
            ~{{ tier.turnaround_days }} days turnaround
          </div>
        </button>
      </div>
    </div>

    <!-- Error Banner -->
    <div
      v-if="errorMessage"
      role="alert"
      class="mt-4 rounded-xl border border-status-danger-border bg-status-danger-bg p-3 text-xs text-status-danger-text"
    >
      {{ errorMessage }}
    </div>

    <!-- Payment Mode Tabs -->
    <div class="mt-5">
      <div class="flex border-b border-subtle gap-6 text-xs font-semibold">
        <button
          type="button"
          class="pb-2.5 transition border-b-2"
          :class="activeTab === 'hosted'
            ? 'border-accent-text text-accent-text'
            : 'border-transparent text-muted hover:text-heading'"
          @click="activeTab = 'hosted'"
        >
          Online Gateway (MTN & Orange)
        </button>
        <button
          type="button"
          class="pb-2.5 transition border-b-2"
          :class="activeTab === 'direct_push'
            ? 'border-accent-text text-accent-text'
            : 'border-transparent text-muted hover:text-heading'"
          @click="activeTab = 'direct_push'"
        >
          Direct Mobile Money Push
        </button>
      </div>

      <!-- Tab 1: Hosted Checkout -->
      <div
        v-if="activeTab === 'hosted'"
        class="mt-4 space-y-3"
      >
        <p class="text-xs text-body">
          You will be redirected to the secure payment portal to complete payment with your MTN Mobile Money or Orange Money account.
        </p>

        <!-- Ready Link Banner if initiated and active -->
        <div
          v-if="currentPayment?.checkout_url && isPaymentActive"
          class="rounded-xl border border-accent-soft-border bg-accent-soft p-3.5 space-y-2 text-xs text-accent-soft-text"
        >
          <div class="flex items-center gap-2 font-bold">
            <span class="inline-flex h-2 w-2 rounded-full bg-accent-text animate-ping" />
            Payment checkout link is ready
          </div>
          <div class="flex flex-wrap items-center gap-2 pt-1">
            <a
              :href="currentPayment.checkout_url"
              target="_blank"
              rel="noopener noreferrer"
              class="btn-primary text-xs"
            >
              Open Checkout Portal ↗
            </a>
            <button
              type="button"
              class="btn-secondary text-xs"
              :disabled="busy"
              @click="checkStatus"
            >
              {{ busy ? 'Checking…' : 'I have completed payment — Verify status' }}
            </button>
          </div>
        </div>

        <div
          v-else
          class="flex flex-wrap items-center gap-3"
        >
          <button
            type="button"
            class="btn-primary"
            :disabled="busy"
            @click="startHostedCheckout"
          >
            {{ busy ? 'Redirecting…' : (currentPayment?.status === 'failed' ? `Retry payment (${formatXAF(amountDue)}) →` : `Proceed to checkout (${formatXAF(amountDue)}) →`) }}
          </button>
          <button
            v-if="currentPayment?.status === 'failed'"
            type="button"
            class="text-xs text-muted underline hover:text-heading"
            :disabled="busy"
            @click="checkStatus"
          >
            I already paid on the portal — Check status
          </button>
          <span v-else class="text-xs text-muted">Instant verification upon return</span>
        </div>
      </div>

      <!-- Tab 2: Direct USSD Push -->
      <div
        v-if="activeTab === 'direct_push'"
        class="mt-4 space-y-4"
      >
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-xs font-semibold text-body mb-1">
              Select Operator
            </label>
            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                class="rounded-lg border p-2 text-xs font-bold text-center transition"
                :class="operator === 'mtn_momo'
                  ? 'border-status-warning-text bg-status-warning-bg text-status-warning-text ring-2 ring-status-warning-border'
                  : 'border-subtle bg-surface-card text-body hover:bg-surface-muted'"
                @click="operator = 'mtn_momo'"
              >
                MTN MoMo
              </button>
              <button
                type="button"
                class="rounded-lg border p-2 text-xs font-bold text-center transition"
                :class="operator === 'orange_money'
                  ? 'border-status-orange-text bg-status-orange-bg text-status-orange-text ring-2 ring-status-orange-border'
                  : 'border-subtle bg-surface-card text-body hover:bg-surface-muted'"
                @click="operator = 'orange_money'"
              >
                Orange Money
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-body mb-1">
              Phone Number (Cameroon)
            </label>
            <input
              v-model="phoneNumber"
              type="tel"
              placeholder="e.g. 677123456"
              class="input-field"
              :disabled="busy || pushActive"
            />
          </div>
        </div>

        <!-- Waiting Indicator when Direct Push Prompt is Sent -->
        <div
          v-if="pushActive && isPaymentActive"
          class="rounded-xl border border-accent-soft-border bg-accent-soft p-3.5 space-y-2 text-xs text-accent-soft-text"
        >
          <div class="flex items-center gap-2 font-bold">
            <span class="inline-flex h-2 w-2 rounded-full bg-accent-text animate-ping" />
            USSD Prompt Sent to {{ phoneNumber }}!
          </div>
          <p>
            Please check your handset screen or dial <strong>{{ operator === 'mtn_momo' ? '*126#' : '#150#' }}</strong> to enter your PIN and approve <strong>{{ formatXAF(amountDue) }}</strong>.
          </p>
          <div class="pt-1 flex items-center gap-2">
            <button
              type="button"
              class="btn-secondary text-xs"
              :disabled="busy"
              @click="checkStatus"
            >
              {{ busy ? 'Checking…' : 'I have approved — Check status' }}
            </button>
            <span class="text-[11px] text-muted">Auto-checking every few seconds</span>
          </div>
        </div>

        <button
          v-else
          type="button"
          class="btn-primary"
          :disabled="busy || !phoneNumber.trim()"
          @click="startDirectPush"
        >
          {{ busy ? 'Sending prompt…' : (currentPayment?.status === 'failed' ? `Retry USSD push to phone (${formatXAF(amountDue)})` : `Send USSD push to phone (${formatXAF(amountDue)})`) }}
        </button>
      </div>
    </div>

    <!-- Developer / Tester Simulator Card (Available in Fake mode or demo environments) -->
    <div
      v-if="isFakeDriver"
      class="mt-6 rounded-xl border border-dashed border-strong bg-surface-card p-4 text-xs"
    >
      <div class="flex items-center justify-between">
        <span class="font-bold text-heading">🛠 Test Mode Gateway Simulator</span>
        <span class="text-[10px] uppercase font-semibold text-muted">Driver: fake</span>
      </div>
      <p class="mt-1 text-muted">
        You can simulate immediate payment outcomes without entering real MoMo credentials or receiving SMS.
      </p>
      <div class="mt-3 flex flex-wrap gap-2">
        <button
          type="button"
          class="btn-secondary text-xs text-status-success-text border-status-success-border hover:bg-status-success-bg"
          :disabled="busy"
          @click="simulateOutcome('successful')"
        >
          ✓ Simulate Success
        </button>
        <button
          type="button"
          class="btn-secondary text-xs text-status-danger-text border-status-danger-border hover:bg-status-danger-bg"
          :disabled="busy"
          @click="simulateOutcome('failed')"
        >
          ✕ Simulate Failure
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import TierBadge, { formatXAF } from '@/components/TierBadge.vue'
import { parseApiError } from '@/utils/error'
import {
  initiatePayment,
  reopenRequest,
  switchRequestTier,
  verifyPayment
} from '@/services/requests'
import type { Payment, Request, RequestTypeTier } from '@/types'

const props = withDefaults(
  defineProps<{
    request: Request
    activeTiers?: RequestTypeTier[]
    availableTiers?: RequestTypeTier[]
  }>(),
  {
    activeTiers: () => [],
    availableTiers: () => []
  }
)

const emit = defineEmits<{
  (e: 'payment-completed', request: Request): void
  (e: 'request-updated', request: Request): void
  (e: 'updated', request: Request): void
}>()

const activeTab = ref<'hosted' | 'direct_push'>('hosted')
const operator = ref<'mtn_momo' | 'orange_money'>('mtn_momo')
const phoneNumber = ref('')
const busy = ref(false)
const errorMessage = ref('')
const showTierSwitcher = ref(false)
const pushActive = ref(false)
const currentPayment = ref<Payment | null>(props.request.latest_payment ?? null)

const isPaymentActive = computed(() => {
  return (
    currentPayment.value !== null &&
    (currentPayment.value.status === 'pending' || currentPayment.value.status === 'processing')
  )
})

watch(
  () => props.request.latest_payment,
  (latest) => {
    currentPayment.value = latest ?? null
  }
)

let pollTimer: ReturnType<typeof setInterval> | null = null

const amountDue = computed(() => {
  if (props.request.amount_due != null) return props.request.amount_due
  const total = props.request.pending_upgrade_tier?.fee_amount ?? props.request.fee_amount ?? 0
  const paid = props.request.amount_paid ?? 0
  return Math.max(0, total - paid)
})

const displayTierName = computed(() =>
  props.request.pending_upgrade_tier?.name ?? props.request.tier_name ?? 'Standard'
)

const displayPriority = computed(() =>
  props.request.pending_upgrade_tier?.priority ?? props.request.tier_priority ?? 1
)

const displayTurnaroundDays = computed(() =>
  props.request.pending_upgrade_tier?.turnaround_days ?? props.request.turnaround_days ?? 3
)

const displayFeeAmount = computed(() =>
  props.request.pending_upgrade_tier?.fee_amount ?? props.request.fee_amount ?? 0
)

const availableTiers = computed(() => {
  const tiers = props.activeTiers.length > 0 ? props.activeTiers : props.availableTiers
  return tiers.filter((t) => t.is_active !== false)
})

const isFakeDriver = computed(() => {
  return (
    !currentPayment.value ||
    currentPayment.value.provider === 'fake' ||
    import.meta.env.DEV
  )
})

async function selectTier(tier: RequestTypeTier) {
  busy.value = true
  errorMessage.value = ''
  try {
    const updated = await switchRequestTier(props.request.id, tier.id)
    showTierSwitcher.value = false
    emit('request-updated', updated)
    emit('updated', updated)
  } catch (err: unknown) {
    errorMessage.value = parseApiError(err, 'Failed to switch tier.')
  } finally {
    busy.value = false
  }
}

async function cancelUpgrade() {
  busy.value = true
  errorMessage.value = ''
  try {
    const updated = await reopenRequest(props.request.id, { cancel_upgrade: true })
    emit('request-updated', updated)
    emit('updated', updated)
  } catch (err: unknown) {
    errorMessage.value = parseApiError(err, 'Failed to cancel upgrade.')
  } finally {
    busy.value = false
  }
}

async function startHostedCheckout() {
  busy.value = true
  errorMessage.value = ''
  try {
    const payment = await initiatePayment(props.request.id, {
      checkout_mode: 'hosted'
    })
    currentPayment.value = payment

    if (payment.checkout_url) {
      if (typeof window !== 'undefined' && !import.meta.env.TEST) {
        window.location.href = payment.checkout_url
      }
      startPolling()
    } else {
      startPolling()
    }
  } catch (err: unknown) {
    errorMessage.value = parseApiError(err, 'Failed to start payment.')
  } finally {
    busy.value = false
  }
}

async function startDirectPush() {
  if (!phoneNumber.value.trim()) return
  busy.value = true
  errorMessage.value = ''
  try {
    const payment = await initiatePayment(props.request.id, {
      checkout_mode: 'direct_push',
      payment_method: operator.value,
      phone: phoneNumber.value.trim()
    })
    currentPayment.value = payment
    pushActive.value = true
    startPolling()
  } catch (err: unknown) {
    errorMessage.value = parseApiError(err, 'Failed to initiate push prompt.')
  } finally {
    busy.value = false
  }
}

async function checkStatus() {
  if (!currentPayment.value) return
  busy.value = true
  errorMessage.value = ''
  try {
    const result = await verifyPayment(props.request.id, currentPayment.value.id)
    currentPayment.value = result.payment
    if (result.payment.status === 'successful') {
      stopPolling()
      emit('payment-completed', result.request)
      emit('updated', result.request)
    } else if (result.payment.status === 'failed') {
      stopPolling()
      pushActive.value = false
      errorMessage.value = result.payment.failure_reason || 'Payment was declined or cancelled.'
    }
  } catch (err: unknown) {
    errorMessage.value = parseApiError(err, 'Verification failed.')
  } finally {
    busy.value = false
  }
}

async function simulateOutcome(outcome: 'successful' | 'failed') {
  busy.value = true
  errorMessage.value = ''
  try {
    if (!currentPayment.value || currentPayment.value.status !== 'pending') {
      currentPayment.value = await initiatePayment(props.request.id, {
        checkout_mode: 'hosted'
      })
    }
    const result = await verifyPayment(props.request.id, currentPayment.value.id, outcome)
    currentPayment.value = result.payment
    if (result.payment.status === 'successful') {
      stopPolling()
      emit('payment-completed', result.request)
      emit('updated', result.request)
    } else {
      stopPolling()
      errorMessage.value = result.payment.failure_reason || 'Simulated payment failed.'
    }
  } catch (err: unknown) {
    errorMessage.value = parseApiError(err, 'Simulation failed.')
  } finally {
    busy.value = false
  }
}

function startPolling() {
  stopPolling()
  pollTimer = setInterval(async () => {
    if (currentPayment.value && (currentPayment.value.status === 'pending' || currentPayment.value.status === 'processing')) {
      try {
        const result = await verifyPayment(props.request.id, currentPayment.value.id)
        currentPayment.value = result.payment
        if (result.payment.status === 'successful') {
          stopPolling()
          emit('payment-completed', result.request)
          emit('updated', result.request)
        } else if (result.payment.status === 'failed') {
          stopPolling()
          pushActive.value = false
          errorMessage.value = result.payment.failure_reason || 'Payment was declined.'
        }
      } catch {
        // Continue polling silently
      }
    }
  }, 8000)
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

onMounted(() => {
  if (
    props.request.latest_payment &&
    (props.request.latest_payment.status === 'pending' || props.request.latest_payment.status === 'processing')
  ) {
    checkStatus()
    startPolling()
  }
})

onUnmounted(() => {
  stopPolling()
})
</script>
