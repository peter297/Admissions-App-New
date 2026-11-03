import { useForm } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = { classId: number; className: string; onClose: () => void };

export default function ClassImportDialog({ classId, className, onClose }: Props) {
    const form = useForm<{ file: File | null }>({ file: null });

    function upload() {
        if (!form.data.file) return;

        // Inertia switches to a multipart upload by itself when the data contains a file.
        form.post(`/classes/${classId}/import`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Import learners into {className}</DialogTitle>
                    <DialogDescription>
                        Upload an Excel or CSV file. New learners are added as Active in the current academic year.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-4">
                    <Button asChild variant="outline" className="w-fit">
                        {/* A normal link, because this is a file download and not an Inertia page. */}
                        <a href="/learners/import-template">Download the template</a>
                    </Button>

                    <div className="flex flex-col gap-2">
                        <Label>File</Label>
                        <Input
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                        />
                    </div>

                    {form.errors.file && (
                        <pre className="max-h-64 overflow-auto rounded-md border border-destructive/40 p-3 text-sm whitespace-pre-wrap text-destructive">
                            {form.errors.file}
                        </pre>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={upload} disabled={!form.data.file || form.processing}>
                        {form.processing ? 'Importing...' : 'Import'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
