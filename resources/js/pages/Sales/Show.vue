<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { 
    Printer, 
    ArrowLeft, 
    FileText, 
    CheckCircle2, 
    Clock, 
    Truck, 
    ShieldCheck, 
    AlertTriangle, 
    Building2, 
    Phone, 
    Mail, 
    MapPin, 
    Calendar, 
    Download,
    Check,
    X,
    ExternalLink,
    PackageCheck
} from '@lucide/vue';

interface SaleItem {
    id: number;
    sku: string;
    product_name: string;
    quantity: number;
    unit_price: number;
    subtotal: number;
    product?: {
        id: number;
        name: string;
        sku: string;
    } | null;
}

interface OrderFulfillment {
    id: number;
    fulfillment_number: string;
    fulfillment_type: string;
    recipient_name: string;
    contact_number: string;
    delivery_address?: string | null;
    delivery_schedule?: string | null;
    instructions?: string | null;
    status: 'Preparing' | 'Ready for Pickup' | 'Dispatched' | 'Delivered' | 'Cancelled';
    proof_of_delivery?: string | null;
    proof_url?: string | null;
    dispatched_at?: string | null;
    delivered_at?: string | null;
    notes?: string | null;
    handled_by?: { name: string } | null;
}

interface Sale {
    id: number;
    invoice_number: string;
    client_name: string;
    contact_person?: string | null;
    email?: string | null;
    phone?: string | null;
    address?: string | null;
    sale_date: string;
    subtotal: number;
    discount: number;
    tax_amount: number;
    grand_total: number;
    status: string;
    payment_method?: string | null;
    payment_reference?: string | null;
    amount_paid?: number | null;
    outstanding_balance?: number | null;
    payment_date?: string | null;
    payment_status?: string | null;
    proof_of_payment?: string | null;
    proof_url?: string | null;
    notes?: string | null;
    verified_at?: string | null;
    verified_by?: { name: string } | null;
    processed_by?: { name: string } | null;
    items?: SaleItem[];
    fulfillment?: OrderFulfillment | null;
}

const props = defineProps<{
    sale: Sale;
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug || (page.props as any).team?.slug || '');

const formatCurrency = (val: number | string | undefined | null) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(val || 0));
};

const formatDate = (dateStr?: string | null) => {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return dateStr;
    }
};

const formatDateTime = (dateStr?: string | null) => {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateStr;
    }
};

const triggerPrint = () => {
    window.print();
};

// Payment Verification State
const showVerifyModal = ref(false);
const verifyDecision = ref<'Verified' | 'Rejected'>('Verified');
const verifyNotes = ref('');
const isSubmittingVerify = ref(false);

const submitPaymentVerification = () => {
    isSubmittingVerify.value = true;
    router.post(`/${teamSlug.value}/sales/${props.sale.id}/verify-payment`, {
        status: verifyDecision.value,
        notes: verifyNotes.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSubmittingVerify.value = false;
            showVerifyModal.value = false;
            verifyNotes.value = '';
        }
    });
};

// Fulfillment Modal State
const showFulfillmentModal = ref(false);
const fulfillmentTargetStatus = ref<string>(props.sale.fulfillment?.status || 'Dispatched');
const fulfillmentProofFile = ref<File | null>(null);
const fulfillmentNotes = ref('');
const isSubmittingFulfillment = ref(false);

const onProofFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    fulfillmentProofFile.value = input.files?.[0] || null;
};

const submitFulfillmentStatus = () => {
    if (!props.sale.fulfillment) return;
    isSubmittingFulfillment.value = true;

    router.patch(`/${teamSlug.value}/fulfillments/${props.sale.fulfillment.id}/status`, {
        status: fulfillmentTargetStatus.value,
        notes: fulfillmentNotes.value || undefined,
        proof_of_delivery: fulfillmentProofFile.value || undefined,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            isSubmittingFulfillment.value = false;
            showFulfillmentModal.value = false;
            fulfillmentProofFile.value = null;
            fulfillmentNotes.value = '';
        }
    });
};
</script>

<template>
    <Head :title="`Invoice ${sale.invoice_number} — MR-PRIDE`" />

    <div class="workspace-page max-w-6xl mx-auto p-4 sm:p-6 print:p-0 print:max-w-none">
        <!-- Top Action Bar (Hidden in Print) -->
        <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 print:hidden">
            <Link
                :href="`/${teamSlug}/sales`"
                class="inline-flex items-center gap-2 text-xs font-semibold text-gray-600 hover:text-gray-900 transition-colors"
            >
                <ArrowLeft class="w-4 h-4" />
                <span>Back to Sales Transactions</span>
            </Link>

            <div class="flex items-center gap-3">
                <!-- Payment Verification Action -->
                <SpecularButton
                    v-if="sale.payment_status === 'Pending Verification'"
                    variant="brand"
                    size="sm"
                    :radius="8"
                    @click="showVerifyModal = true"
                >
                    <ShieldCheck class="w-4 h-4 mr-1.5" />
                    <span>Audit / Verify Payment</span>
                </SpecularButton>

                <!-- Fulfillment Progress Action -->
                <SpecularButton
                    v-if="sale.fulfillment && sale.fulfillment.status !== 'Delivered'"
                    variant="brand"
                    size="sm"
                    :radius="8"
                    @click="showFulfillmentModal = true"
                >
                    <Truck class="w-4 h-4 mr-1.5" />
                    <span>Update Delivery Status</span>
                </SpecularButton>

                <SpecularButton
                    variant="secondary"
                    size="sm"
                    :radius="8"
                    @click="triggerPrint"
                >
                    <Printer class="w-4 h-4 mr-1.5" />
                    <span>Print Formal Invoice</span>
                </SpecularButton>
            </div>
        </div>

        <!-- Printable Invoice Container -->
        <div class="bg-white rounded-xl border border-gray-200 print:border-none print:shadow-none p-6 sm:p-10 space-y-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start border-b-2 border-[#4b5320] pb-6 gap-6">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-[#4b5320] tracking-tight">MR-PRIDE ELECTRICAL SUPPLIES</h1>
                    <p class="text-xs text-gray-500 font-semibold uppercase mt-0.5">Industrial & Commercial Electrical Solutions</p>
                    <p class="text-xs text-gray-500 mt-1">Direct Electrical Importers & Authorized Distributor</p>
                    <p class="text-xs text-gray-500">Contact: +63 (2) 8123-4567 | info@mrpelectrodesk.ph</p>
                </div>

                <div class="sm:text-right">
                    <span class="inline-block px-3 py-1 bg-[#4b5320] text-white font-black text-xl tracking-widest rounded-lg mb-2">
                        SALES INVOICE
                    </span>
                    <p class="text-sm font-bold text-gray-900 font-mono">Invoice #: {{ sale.invoice_number }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Date: <strong class="text-gray-800">{{ formatDateTime(sale.sale_date) }}</strong></p>
                    <div class="mt-2 flex items-center justify-start sm:justify-end gap-2">
                        <span class="text-xs font-semibold text-gray-500">Transaction Status:</span>
                        <StatusBadge :status="sale.status" size="sm" />
                    </div>
                </div>
            </div>

            <!-- Client & Delivery / Payment Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Customer Details -->
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 text-xs space-y-1.5">
                    <div class="flex items-center gap-1.5 font-bold uppercase tracking-wider text-[#4b5320] mb-2">
                        <Building2 class="w-3.5 h-3.5" />
                        <span>Billed Customer</span>
                    </div>
                    <p class="text-sm font-bold text-gray-900">{{ sale.client_name }}</p>
                    <p v-if="sale.contact_person" class="text-gray-600">Contact: {{ sale.contact_person }}</p>
                    <p v-if="sale.phone" class="text-gray-600">Phone: {{ sale.phone }}</p>
                    <p v-if="sale.email" class="text-gray-600">Email: {{ sale.email }}</p>
                    <p v-if="sale.address" class="text-gray-600">Address: {{ sale.address }}</p>
                </div>

                <!-- Payment Status Card -->
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 text-xs space-y-1.5">
                    <div class="flex items-center gap-1.5 font-bold uppercase tracking-wider text-[#4b5320] mb-2">
                        <ShieldCheck class="w-3.5 h-3.5" />
                        <span>Payment Audit</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Method:</span>
                        <span class="font-bold text-gray-900">{{ sale.payment_method || 'Bank Transfer' }}</span>
                    </div>
                    <div v-if="sale.payment_reference" class="flex justify-between">
                        <span class="text-gray-500">Reference #:</span>
                        <span class="font-mono font-bold text-gray-900">{{ sale.payment_reference }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Verification:</span>
                        <span
                            :class="[
                                'px-2 py-0.5 rounded text-[11px] font-bold',
                                sale.payment_status === 'Verified' ? 'bg-emerald-100 text-emerald-800' :
                                sale.payment_status === 'Rejected' ? 'bg-rose-100 text-rose-800' :
                                'bg-amber-100 text-amber-800'
                            ]"
                        >
                            {{ sale.payment_status || 'Verified' }}
                        </span>
                    </div>
                    <div v-if="sale.verified_by" class="flex justify-between">
                        <span class="text-gray-500">Verified By:</span>
                        <span class="font-medium text-gray-800">{{ sale.verified_by.name }}</span>
                    </div>
                    <div v-if="sale.proof_url" class="pt-2 border-t border-gray-200 print:hidden">
                        <a
                            :href="sale.proof_url"
                            target="_blank"
                            class="inline-flex items-center gap-1 text-[11px] font-bold text-[#4b5320] hover:underline"
                        >
                            <ExternalLink class="w-3.5 h-3.5" />
                            <span>View Proof of Payment Attachment</span>
                        </a>
                    </div>
                </div>

                <!-- Fulfillment & Dispatch Tracking Card -->
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 text-xs space-y-1.5">
                    <div class="flex items-center gap-1.5 font-bold uppercase tracking-wider text-[#4b5320] mb-2">
                        <Truck class="w-3.5 h-3.5" />
                        <span>Order Fulfillment</span>
                    </div>
                    <div v-if="sale.fulfillment">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Tracking #:</span>
                            <span class="font-mono font-bold text-gray-900">{{ sale.fulfillment.fulfillment_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Type:</span>
                            <span class="font-semibold text-gray-900">{{ sale.fulfillment.fulfillment_type }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Fulfillment Status:</span>
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded text-[11px] font-bold',
                                    sale.fulfillment.status === 'Delivered' ? 'bg-emerald-100 text-emerald-800' :
                                    sale.fulfillment.status === 'Dispatched' ? 'bg-blue-100 text-blue-800' :
                                    'bg-amber-100 text-amber-800'
                                ]"
                            >
                                {{ sale.fulfillment.status }}
                            </span>
                        </div>
                        <div v-if="sale.fulfillment.recipient_name" class="flex justify-between">
                            <span class="text-gray-500">Recipient:</span>
                            <span class="font-medium text-gray-900">{{ sale.fulfillment.recipient_name }}</span>
                        </div>
                        <div v-if="sale.fulfillment.contact_number" class="flex justify-between">
                            <span class="text-gray-500">Contact:</span>
                            <span class="text-gray-800">{{ sale.fulfillment.contact_number }}</span>
                        </div>
                        <div v-if="sale.fulfillment.delivery_address" class="text-gray-600 mt-1">
                            <span class="text-gray-500 block">Address:</span>
                            {{ sale.fulfillment.delivery_address }}
                        </div>
                        <div v-if="sale.fulfillment.proof_url" class="pt-2 border-t border-gray-200 print:hidden">
                            <a
                                :href="sale.fulfillment.proof_url"
                                target="_blank"
                                class="inline-flex items-center gap-1 text-[11px] font-bold text-[#4b5320] hover:underline"
                            >
                                <ExternalLink class="w-3.5 h-3.5" />
                                <span>View Proof of Delivery File</span>
                            </a>
                        </div>
                    </div>
                    <div v-else class="text-gray-400 py-3 text-center">
                        No fulfillment record initialized.
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div>
                <h4 class="text-xs font-bold uppercase text-[#4b5320] tracking-wider mb-2">PURCHASED PRODUCTS & LINE ITEMS</h4>
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-100 text-gray-700 border-b border-gray-300">
                            <th class="py-2.5 px-3 font-bold w-12 text-center">#</th>
                            <th class="py-2.5 px-3 font-bold">Item Description</th>
                            <th class="py-2.5 px-3 font-bold w-28">SKU</th>
                            <th class="py-2.5 px-3 font-bold text-center w-16">Qty</th>
                            <th class="py-2.5 px-3 font-bold text-right w-24">Unit Price</th>
                            <th class="py-2.5 px-3 font-bold text-right w-28">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="(item, idx) in sale.items" :key="item.id || idx" class="hover:bg-gray-50">
                            <td class="py-3 px-3 text-center text-gray-400 font-mono">{{ idx + 1 }}</td>
                            <td class="py-3 px-3">
                                <p class="font-bold text-gray-900">{{ item.product_name }}</p>
                            </td>
                            <td class="py-3 px-3 font-mono text-gray-500 text-[11px]">{{ item.sku }}</td>
                            <td class="py-3 px-3 text-center font-bold text-gray-900">{{ item.quantity }}</td>
                            <td class="py-3 px-3 text-right font-medium text-gray-800">{{ formatCurrency(item.unit_price) }}</td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-gray-900">{{ formatCurrency(item.subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals & Balances -->
            <div class="flex justify-end pt-2">
                <div class="w-full sm:w-80 space-y-2 text-xs">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal:</span>
                        <span class="font-mono font-bold">{{ formatCurrency(sale.subtotal) }}</span>
                    </div>
                    <div v-if="sale.discount && sale.discount > 0" class="flex justify-between text-rose-600">
                        <span>Discount:</span>
                        <span class="font-mono font-bold">-{{ formatCurrency(sale.discount) }}</span>
                    </div>
                    <div v-if="sale.tax_amount && sale.tax_amount > 0" class="flex justify-between text-gray-600">
                        <span>Value Added Tax (12% VAT):</span>
                        <span class="font-mono font-bold">{{ formatCurrency(sale.tax_amount) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t-2 border-gray-900 text-sm">
                        <span class="font-black text-gray-900 uppercase">Grand Total:</span>
                        <span class="font-black text-base text-[#4b5320] font-mono">{{ formatCurrency(sale.grand_total) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-700 pt-1">
                        <span>Amount Paid:</span>
                        <span class="font-mono font-bold text-emerald-700">{{ formatCurrency(sale.amount_paid) }}</span>
                    </div>
                    <div v-if="Number(sale.outstanding_balance || 0) > 0" class="flex justify-between text-rose-700 font-bold bg-rose-50 p-2 rounded-lg border border-rose-200">
                        <span>Outstanding Balance:</span>
                        <span class="font-mono">{{ formatCurrency(sale.outstanding_balance) }}</span>
                    </div>
                    <div v-else class="flex justify-between text-emerald-700 font-bold bg-emerald-50 p-2 rounded-lg border border-emerald-200">
                        <span>Payment Status:</span>
                        <span>Fully Settled</span>
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            <div v-if="sale.notes" class="pt-4 border-t border-gray-200 text-xs text-gray-600">
                <h5 class="font-bold uppercase text-gray-700 text-[10px] mb-1">Invoice Remarks & Audit Trail:</h5>
                <p class="whitespace-pre-line leading-relaxed bg-gray-50 p-3 rounded-lg border border-gray-200">{{ sale.notes }}</p>
            </div>

            <!-- Signatures -->
            <div class="pt-8 border-t border-gray-200 grid grid-cols-2 gap-8 text-xs">
                <div>
                    <p class="text-gray-500 mb-8">Issued By:</p>
                    <div class="border-b border-gray-400 w-48 mb-1"></div>
                    <p class="font-bold text-gray-900">{{ sale.processed_by?.name || 'Authorized Cashier / Staff' }}</p>
                    <p class="text-gray-400 text-[10px]">MR-PRIDE Electrical Supplies</p>
                </div>
                <div>
                    <p class="text-gray-500 mb-8">Customer Conforme / Received Goods:</p>
                    <div class="border-b border-gray-400 w-48 mb-1"></div>
                    <p class="font-bold text-gray-900">{{ sale.fulfillment?.recipient_name || sale.client_name }}</p>
                    <p class="text-gray-400 text-[10px]">Signature over Printed Name</p>
                </div>
            </div>
        </div>

        <!-- VERIFY PAYMENT MODAL -->
        <div v-if="showVerifyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div class="bg-white border border-gray-200 p-6 rounded-xl w-full max-w-md shadow-xl animate-in fade-in duration-150">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-3 bg-amber-100 text-amber-800 rounded-xl">
                        <ShieldCheck class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Verify Customer Payment</h3>
                        <p class="text-xs text-gray-500">Invoice: {{ sale.invoice_number }} ({{ sale.client_name }})</p>
                    </div>
                </div>

                <div class="bg-gray-50 p-3 rounded-xl border border-gray-200 text-xs space-y-1.5 mb-4">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Claimed Amount:</span>
                        <span class="font-semibold text-gray-900">{{ formatCurrency(sale.amount_paid) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Payment Method:</span>
                        <span class="font-semibold text-gray-900">{{ sale.payment_method }}</span>
                    </div>
                    <div v-if="sale.payment_reference" class="flex justify-between">
                        <span class="text-gray-500">Reference:</span>
                        <span class="font-mono font-semibold text-gray-900">{{ sale.payment_reference }}</span>
                    </div>
                    <div v-if="sale.proof_url" class="pt-2 border-t border-gray-200">
                        <a :href="sale.proof_url" target="_blank" class="text-[#4b5320] font-semibold inline-flex items-center gap-1 hover:underline">
                            <ExternalLink class="w-3.5 h-3.5" />
                            <span>Open Proof of Payment Document</span>
                        </a>
                    </div>
                </div>

                <div class="space-y-3 mb-4">
                    <label class="block text-xs font-semibold text-gray-700">Verification Decision</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label
                            :class="verifyDecision === 'Verified' ? 'border-emerald-500 bg-emerald-50 text-emerald-900 font-bold' : 'border-gray-200 bg-white text-gray-700'"
                            class="border rounded-xl p-3 cursor-pointer flex items-center gap-2 text-xs transition-colors"
                        >
                            <input type="radio" v-model="verifyDecision" value="Verified" class="accent-emerald-600" />
                            <span>Approve & Verify</span>
                        </label>

                        <label
                            :class="verifyDecision === 'Rejected' ? 'border-rose-500 bg-rose-50 text-rose-900 font-bold' : 'border-gray-200 bg-white text-gray-700'"
                            class="border rounded-xl p-3 cursor-pointer flex items-center gap-2 text-xs transition-colors"
                        >
                            <input type="radio" v-model="verifyDecision" value="Rejected" class="accent-rose-600" />
                            <span>Reject Payment</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Audit Notes / Reason</label>
                        <textarea
                            v-model="verifyNotes"
                            rows="2"
                            class="w-full bg-white border border-gray-200 rounded-lg p-2 text-xs text-gray-900 focus:border-[#4b5320] focus:outline-none"
                            placeholder="Optional notes or remarks regarding the verification..."
                        ></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                    <SpecularButton
                        type="button"
                        variant="secondary"
                        size="md"
                        :radius="10"
                        @click="showVerifyModal = false"
                    >
                        Cancel
                    </SpecularButton>
                    <SpecularButton
                        type="button"
                        variant="brand"
                        size="md"
                        :radius="10"
                        :disabled="isSubmittingVerify"
                        @click="submitPaymentVerification"
                    >
                        {{ isSubmittingVerify ? 'Saving...' : 'Save Decision' }}
                    </SpecularButton>
                </div>
            </div>
        </div>

        <!-- UPDATE FULFILLMENT STATUS MODAL -->
        <div v-if="showFulfillmentModal && sale.fulfillment" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div class="bg-white border border-gray-200 p-6 rounded-xl w-full max-w-md shadow-xl animate-in fade-in duration-150">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-3 bg-blue-100 text-blue-800 rounded-xl">
                        <Truck class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Update Fulfillment Status</h3>
                        <p class="text-xs text-gray-500">Tracking: {{ sale.fulfillment.fulfillment_number }}</p>
                    </div>
                </div>

                <div class="space-y-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">New Fulfillment Status</label>
                        <select
                            v-model="fulfillmentTargetStatus"
                            class="w-full bg-white border border-gray-200 rounded-lg p-2 text-xs text-gray-900 focus:border-[#4b5320] focus:outline-none"
                        >
                            <option value="Preparing">Preparing Order</option>
                            <option value="Ready for Pickup">Ready for Customer Pickup</option>
                            <option value="Dispatched">Dispatched / Out for Delivery</option>
                            <option value="Delivered">Delivered & Confirmed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Proof of Delivery / Waybill (Optional)</label>
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                            class="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#4b5320] file:text-white hover:file:bg-[#3d431a] cursor-pointer"
                            @change="onProofFileChange"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Delivery Dispatch Notes</label>
                        <textarea
                            v-model="fulfillmentNotes"
                            rows="2"
                            class="w-full bg-white border border-gray-200 rounded-lg p-2 text-xs text-gray-900 focus:border-[#4b5320] focus:outline-none"
                            placeholder="Courier name, rider contact, or delivery notes..."
                        ></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                    <SpecularButton
                        type="button"
                        variant="secondary"
                        size="md"
                        :radius="10"
                        @click="showFulfillmentModal = false"
                    >
                        Cancel
                    </SpecularButton>
                    <SpecularButton
                        type="button"
                        variant="brand"
                        size="md"
                        :radius="10"
                        :disabled="isSubmittingFulfillment"
                        @click="submitFulfillmentStatus"
                    >
                        {{ isSubmittingFulfillment ? 'Updating...' : 'Update Fulfillment' }}
                    </SpecularButton>
                </div>
            </div>
        </div>
    </div>
</template>
