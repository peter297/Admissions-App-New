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
type ListType = 'class' | 'lunch' | 'transport';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classId: number;
    academicYearId: number | null;
    columns: Column[];
};

// Starting selections. Any key that does not exist on the server is ignored.
const PRESETS: Record<string, string[]> = {
    class: ['assessment_number', 'full_name', 'admission_number', 'citizenship', 'gender', 'kemis_code', 'lunch', 'status'],
    knec: ['assessment_number', 'full_name', 'citizenship', 'gender'],
    lunch: ['full_name', 'admission_number', 'gender'],
    transport: ['full_name', 'admission_number', 'gender', 'transport_route', 'bus'],
};

const STORAGE_KEY = 'class-export-columns';

function savedColumns(valid: string[]): string[] | null {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const parsed = (JSON.parse(raw) as string[]).filter((k) => valid.includes(k));
        return parsed.length ? parsed : null;
    } catch {
        return null; // storage can be unavailable; fall back to the preset
    }
}

export default function ClassExportDialog({ open, onOpenChange, classId, academicYearId, columns }: Props) {
    const validKeys = columns.map((c) => c.key);

    const [type, setType] = useState<ListType>('class');
    const [format, setFormat] = useState('xlsx');
    const [status, setStatus] = useState('active');
    const [includeTitle, setIncludeTitle] = useState(true);
    const [selected, setSelected] = useState<string[]>(
        () => savedColumns(validKeys) ?? PRESETS.class.filter((k) => validKeys.includes(k)),
    );

    function applyPreset(name: string) {
        setSelected(PRESETS[name].filter((k) => validKeys.includes(k)));
    }

    function changeType(next: ListType) {
        setType(next);
        // Lunch and transport lists start from their own sensible columns.
        if (next === 'class') setSelected(savedColumns(validKeys) ?? PRESETS.class.filter((k) => validKeys.includes(k)));
        else applyPreset(next);
    }

    function toggle(key: string, checked: boolean) {
        setSelected((prev) => (checked ? [...prev, key] : prev.filter((k) => k !== key)));
    }

    function download() {
        if (selected.length === 0 || !academicYearId) return;

        if (type === 'class') {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(selected));
            } catch {
                // not critical
            }
        }

        const params = new URLSearchParams({
            format,
            type,
            status,
            academic_year_id: String(academicYearId),
            include_title: includeTitle ? '1' : '0',
        });
        selected.forEach((k) => params.append('columns[]', k));

        // A normal browser navigation, because this is a file download, not an Inertia page.
        window.location.href = `/classes/${classId}/export?${params.toString()}`;
        onOpenChange(false);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Download list</DialogTitle>
                    <DialogDescription>Choose the list, the format and the columns to include.</DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="flex flex-col gap-2">
                        <Label>List</Label>
                        <Select value={type} onValueChange={(v) => changeType(v as ListType)}>
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="class">Class list</SelectItem>
                                <SelectItem value="lunch">Lunch list</SelectItem>
                                <SelectItem value="transport">Transport list</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="flex flex-col gap-2">
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

                    <div className="flex flex-col gap-2">
                        <Label>Learners</Label>
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active only</SelectItem>
                                <SelectItem value="all">All statuses</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                                <SelectItem value="transferred">Transferred</SelectItem>
                                <SelectItem value="graduated">Graduated</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {format === 'xlsx' && (
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={includeTitle} onCheckedChange={(v) => setIncludeTitle(v === true)} />
                        Include the class name at the top of the sheet
                    </label>
                )}

                <div className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Label>Columns ({selected.length} selected)</Label>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" size="sm" variant="outline" onClick={() => applyPreset('knec')}>
                                KNEC registration
                            </Button>
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

                    <p className="text-xs text-muted-foreground">
                        Columns always appear in the order listed here.
                    </p>
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
