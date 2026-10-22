<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Mail, Phone, MapPin, Send, CheckCircle2 } from '@lucide/vue';
import { ref } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

const inquirySent = ref(false);
const form = ref({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
});

function handleSubmit() {
    if (!form.value.name || !form.value.email || !form.value.message) {
        return;
    }

    const mailSubject = encodeURIComponent(
        form.value.subject || `Inquiry from ${form.value.name}`,
    );
    const mailBody = encodeURIComponent(
        `Name: ${form.value.name}\nEmail: ${form.value.email}\nPhone: ${form.value.phone || 'N/A'}\n\nMessage:\n${form.value.message}`,
    );

    window.location.href = `mailto:sales.rcjlent@gmail.com?subject=${mailSubject}&body=${mailBody}`;
    inquirySent.value = true;
}
</script>

<template>
    <Head title="Contact Us | MR-Pride Electrical Supplies" />

    <PublicLayout>
        <!-- Army Green Background Page Container -->
        <div
            class="min-h-[calc(100vh-5rem)] bg-[#4b5320] py-12 text-white selection:bg-[#F8B803]/40 sm:py-16"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Page Title -->
                <div class="mb-10 text-center sm:mb-12">
                    <h1
                        class="text-3xl font-extrabold tracking-tight text-[#F8B803] sm:text-4xl"
                    >
                        Contact Us
                    </h1>
                    <p class="mx-auto mt-3 max-w-xl text-base text-white/90">
                        Have a question, feedback, or product inquiry? Reach out
                        to us through the form below or contact us directly.
                    </p>
                </div>

                <!-- 2-Column Generic Layout: Contact Information + Contact Form -->
                <div
                    class="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:items-start"
                >
                    <!-- Left: Contact Information Cards -->
                    <div class="space-y-4 lg:col-span-5">
                        <!-- Email -->
                        <div
                            class="rounded-2xl border border-white/20 bg-white p-5 text-gray-900 shadow-xl dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                        >
                            <div class="flex items-start gap-3.5">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#4b5320]/10 text-[#4b5320] dark:bg-neutral-800 dark:text-[#F8B803]"
                                >
                                    <Mail class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h2
                                        class="text-sm font-bold text-gray-900 dark:text-white"
                                    >
                                        Email
                                    </h2>
                                    <div class="mt-1 space-y-1">
                                        <a
                                            href="mailto:sales.rcjlent@gmail.com"
                                            class="block text-xs font-semibold text-gray-700 hover:text-[#4b5320] hover:underline dark:text-neutral-200 dark:hover:text-[#F8B803]"
                                        >
                                            sales.rcjlent@gmail.com
                                        </a>
                                        <a
                                            href="mailto:malabanan.gary@gmail.com"
                                            class="block text-xs font-semibold text-gray-700 hover:text-[#4b5320] hover:underline dark:text-neutral-200 dark:hover:text-[#F8B803]"
                                        >
                                            malabanan.gary@gmail.com
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Phone -->
                        <div
                            class="rounded-2xl border border-white/20 bg-white p-5 text-gray-900 shadow-xl dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                        >
                            <div class="flex items-start gap-3.5">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#4b5320]/10 text-[#4b5320] dark:bg-neutral-800 dark:text-[#F8B803]"
                                >
                                    <Phone class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h2
                                        class="text-sm font-bold text-gray-900 dark:text-white"
                                    >
                                        Phone
                                    </h2>
                                    <div class="mt-1 space-y-1">
                                        <a
                                            href="tel:09397366907"
                                            class="block font-mono text-xs font-bold text-gray-800 hover:text-[#4b5320] dark:text-neutral-200 dark:hover:text-[#F8B803]"
                                        >
                                            09397366907
                                        </a>
                                        <a
                                            href="tel:022444939"
                                            class="block font-mono text-xs font-bold text-gray-800 hover:text-[#4b5320] dark:text-neutral-200 dark:hover:text-[#F8B803]"
                                        >
                                            022444939
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Address -->
                        <div
                            class="rounded-2xl border border-white/20 bg-white p-5 text-gray-900 shadow-xl dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                        >
                            <div class="flex items-start gap-3.5">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#4b5320]/10 text-[#4b5320] dark:bg-neutral-800 dark:text-[#F8B803]"
                                >
                                    <MapPin class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h2
                                        class="text-sm font-bold text-gray-900 dark:text-white"
                                    >
                                        Address
                                    </h2>
                                    <p
                                        class="mt-1 text-xs leading-relaxed font-semibold text-gray-800 dark:text-neutral-200"
                                    >
                                        #610 ESGUERRA ST. TONDO MANILA
                                    </p>
                                </div>
                            </div>
                        </div>


                    </div>

                    <!-- Right: Generic Contact Form -->
                    <div
                        class="rounded-2xl border border-white/20 bg-white p-6 text-gray-900 shadow-xl sm:p-8 lg:col-span-7 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                    >
                        <h2
                            class="text-lg font-bold text-gray-900 dark:text-white"
                        >
                            Send a Message
                        </h2>
                        <p
                            class="mt-1 mb-6 text-xs text-gray-500 dark:text-neutral-400"
                        >
                            Fill in the details below and we will get back to
                            you as soon as possible.
                        </p>

                        <!-- Success message -->
                        <div
                            v-if="inquirySent"
                            class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-500/20 bg-emerald-50 p-4 text-xs text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"
                        >
                            <CheckCircle2
                                class="mt-0.5 size-4.5 shrink-0 text-emerald-600 dark:text-emerald-400"
                            />
                            <div>
                                <p class="font-bold">Email Ready to Send</p>
                                <p class="mt-0.5 text-xs opacity-90">
                                    Your email client has been prepared with
                                    your message.
                                </p>
                            </div>
                        </div>

                        <form @submit.prevent="handleSubmit" class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <!-- Floating Label: Name -->
                                <div class="relative">
                                    <input
                                        id="contact-name"
                                        v-model="form.name"
                                        type="text"
                                        required
                                        placeholder=" "
                                        class="peer block w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm dark:border-neutral-800 dark:bg-neutral-950 dark:text-white dark:focus:border-[#F8B803] dark:focus:ring-[#F8B803]"
                                    />
                                    <label
                                        for="contact-name"
                                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320] dark:text-neutral-400 dark:peer-focus:text-[#F8B803]"
                                        :class="
                                            form.name
                                                ? '-translate-y-2.5 scale-75 text-[#4b5320] dark:text-[#F8B803]'
                                                : ''
                                        "
                                    >
                                        Name
                                    </label>
                                </div>

                                <!-- Floating Label: Email -->
                                <div class="relative">
                                    <input
                                        id="contact-email"
                                        v-model="form.email"
                                        type="email"
                                        required
                                        placeholder=" "
                                        class="peer block w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm dark:border-neutral-800 dark:bg-neutral-950 dark:text-white dark:focus:border-[#F8B803] dark:focus:ring-[#F8B803]"
                                    />
                                    <label
                                        for="contact-email"
                                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320] dark:text-neutral-400 dark:peer-focus:text-[#F8B803]"
                                        :class="
                                            form.email
                                                ? '-translate-y-2.5 scale-75 text-[#4b5320] dark:text-[#F8B803]'
                                                : ''
                                        "
                                    >
                                        Email
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <!-- Floating Label: Phone Number -->
                                <div class="relative">
                                    <input
                                        id="contact-phone"
                                        v-model="form.phone"
                                        type="tel"
                                        placeholder=" "
                                        class="peer block w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm dark:border-neutral-800 dark:bg-neutral-950 dark:text-white dark:focus:border-[#F8B803] dark:focus:ring-[#F8B803]"
                                    />
                                    <label
                                        for="contact-phone"
                                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320] dark:text-neutral-400 dark:peer-focus:text-[#F8B803]"
                                        :class="
                                            form.phone
                                                ? '-translate-y-2.5 scale-75 text-[#4b5320] dark:text-[#F8B803]'
                                                : ''
                                        "
                                    >
                                        Phone Number
                                    </label>
                                </div>

                                <!-- Floating Label: Subject -->
                                <div class="relative">
                                    <input
                                        id="contact-subject"
                                        v-model="form.subject"
                                        type="text"
                                        placeholder=" "
                                        class="peer block w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm dark:border-neutral-800 dark:bg-neutral-950 dark:text-white dark:focus:border-[#F8B803] dark:focus:ring-[#F8B803]"
                                    />
                                    <label
                                        for="contact-subject"
                                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320] dark:text-neutral-400 dark:peer-focus:text-[#F8B803]"
                                        :class="
                                            form.subject
                                                ? '-translate-y-2.5 scale-75 text-[#4b5320] dark:text-[#F8B803]'
                                                : ''
                                        "
                                    >
                                        Subject
                                    </label>
                                </div>
                            </div>

                            <!-- Floating Label: Message -->
                            <div class="relative">
                                <textarea
                                    id="contact-message"
                                    v-model="form.message"
                                    rows="4"
                                    required
                                    placeholder=" "
                                    class="peer block w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3.5 pt-6 pb-2.5 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm dark:border-neutral-800 dark:bg-neutral-950 dark:text-white dark:focus:border-[#F8B803] dark:focus:ring-[#F8B803]"
                                ></textarea>
                                <label
                                    for="contact-message"
                                    class="pointer-events-none absolute top-4 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320] dark:text-neutral-400 dark:peer-focus:text-[#F8B803]"
                                    :class="
                                        form.message
                                            ? '-translate-y-2.5 scale-75 text-[#4b5320] dark:text-[#F8B803]'
                                            : ''
                                    "
                                >
                                    Message
                                </label>
                            </div>

                            <button
                                type="submit"
                                class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#4b5320] px-6 py-3 text-xs font-bold text-white shadow-md transition-all hover:bg-[#393f18] hover:shadow-lg focus:ring-2 focus:ring-[#F8B803] focus:ring-offset-2 focus:outline-none sm:text-sm"
                            >
                                <Send class="size-4" />
                                <span>Send Message</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
