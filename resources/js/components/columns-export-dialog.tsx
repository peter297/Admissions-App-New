import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Column = { key: string; label: string };

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    url: string;
    columns: Column[];
    defaultColumns: string[];
    /** Filters currently applied on the page, sent along so the file matches what you see. */
    extraParams?: Record<string, string>;
};

export default function ColumnsExportDialog({
    open,
    onOpenChange,
    title,
    url,
    columns,
    defaultColumns,
    extraParams = {},
}: Props) {
    const validKeys = columns.map((c) => c.key);

    const [format, setFormat] = useState('xlsx');
    const [includeTitle, setIncludeTitle] = useState(true);
    const [selected, setSelected] = useState<string[]>(() => defaultColumns.filter((k) => validKeys.includes(k)));

    function toggle(key: string, checked: boolean) {
        setSelected((prev) => (checked ? [...prev, key] : prev.filter((k) => k !== key)));
    }

    function download() {
        if (selected.length === 0) return;

        const params = new URLSearchParams({ ...extraParams, format, include_title: includeTitle ? '1' : '0' });
        selected.forEach((k) => params.append('columns[]', k));

        // A file download, so a normal browser navigation and not an Inertia visit.
        window.location.href = `${url}?${params.toString()}`;
        onOpenChange(false);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        The file contains the applicants matching the filters on the page.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-2 sm:w-48">
                    <Label>Format</Label>
                    <Select value={format} onValueChange={setFormat}>
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="xlsx">Excel</SelectItem>
                            <SelectItem value="pdf">PDF</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {format === 'xlsx' && (
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={includeTitle} onCheckedChange={(v) => setIncludeTitle(v === true)} />
                        Include the title at the top of the sheet
                    </label>
                )}

                <div className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Label>Columns ({selected.length} selected)</Label>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" size="sm" variant="outline" onClick={() => setSelected(validKeys)}>
                                Select all
                            </Button>
                            <Button type="button" size="sm" variant="outline" onClick={() => setSelected([])}>
                                Clear
                            </Button>
                        </div>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2">
                        {columns.map((c) => (
                            <label key={c.key} className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={selected.includes(c.key)}
                                    onCheckedChange={(v) => toggle(c.key, v === true)}
                                />
                                {c.label}
                            </label>
                        ))}
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button onClick={download} disabled={selected.length === 0}>
                        Download
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
