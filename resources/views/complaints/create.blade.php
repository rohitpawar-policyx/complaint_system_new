<x-layouts.app title="Raise Complaint | Complaint Management System">
    <section class="page-heading">
        <p class="eyebrow">Support</p>
        <h1>Raise a complaint</h1>
        <p>Choose a reason and describe the issue. Your complaint priority is assigned by the system.</p>
    </section>
    <section class="content-card complaint-form-card">
        <form method="post" action="{{ route('complaints.store') }}" enctype="multipart/form-data" class="complaint-form">
            @csrf
            <label for="reason_id">Complaint reason</label>
            <select id="reason_id" name="reason_id" required>
                <option value="">Select a reason</option>
                @foreach ($reasons as $reason)
                    <option value="{{ $reason->id }}" @selected(old('reason_id') == $reason->id)>{{ $reason->name }}</option>
                @endforeach
            </select>
            <label for="message">Message</label>
            <textarea id="message" name="message" rows="8" maxlength="10000" required>{{ old('message') }}</textarea>
            <label for="payment_proof"><x-icon name="receipt" /> Payment proof</label>
            <input id="payment_proof" name="payment_proof" type="file" accept=".jpg,.jpeg,.png" required>
            <p class="form-help">Required: a screenshot of your payment (JPG or PNG, 5 MiB max). We'll try to automatically detect the transaction ID from it.</p>
            <label for="attachments"><x-icon name="upload" /> Attachments</label>
            <input id="attachments" name="attachments[]" type="file" accept=".pdf,.jpg,.jpeg,.png" multiple>
            <p class="form-help">Optional: up to 5 PDF, JPG, JPEG, or PNG files, 5 MiB each.</p>
            <button type="submit"><x-icon name="send" /> Submit complaint</button>
        </form>
    </section>
</x-layouts.app>
