import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ImportIndex() {
    const { flash } = usePage<{ flash?: { success?: string } }>().props;
    const form = useForm<{ file: File | null }>({ file: null });

    // Changing the key empties the file input after a successful upload.
    const [inputKey, setInputKey] = useState(0);

    function upload() {
        if (!form.data.file) return;

        form.post('/import', {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                setInputKey((k) => k + 1);
            },
        });
    }

    return (
        <>
            <Head title="Import learners" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Import learners for the whole school</h1>
                    <p className="text-sm text-muted-foreground">
                        Use this to bring in many classes at once, for example when moving over from the old system.
                        Every row says which class the learner belongs to.
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-md border border-green-600/30 bg-green-50 p-3 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">
                        {flash.success}
                    </div>
                )}

                <ol className="list-decimal space-y-2 pl-5 text-sm">
                    <li>Download the template, and read the Instructions sheet inside it.</li>
                    <li>Fill in one row per learner, with the Branch and Class on every row.</li>
                    <li>Upload the file below. If any row has a problem, nothing is imported and you are shown the rows to fix.</li>
                </ol>

                <Button asChild variant="outline" className="w-fit">
                    {/* A normal link, because this is a file download and not an Inertia page. */}
                    <a href="/import/template">Download the template</a>
                </Button>

                <div className="flex max-w-md flex-col gap-2">
                    <Label>File</Label>
                    <Input
                        key={inputKey}
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                    />
                </div>

                {form.errors.file && (
                    <pre className="max-h-96 overflow-auto rounded-md border border-destructive/40 p-3 text-sm whitespace-pre-wrap text-destructive">
                        {form.errors.file}
                    </pre>
                )}

                <div>
                    <Button onClick={upload} disabled={!form.data.file || form.processing}>
                        {form.processing ? 'Importing... this can take a minute' : 'Import learners'}
                    </Button>
                </div>
            </div>
        </>
    );
}
