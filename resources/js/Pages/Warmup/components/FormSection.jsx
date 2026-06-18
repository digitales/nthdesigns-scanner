import { Stack } from '@/Components/ui';

export default function FormSection({ title, children }) {
    return (
        <Stack gap={16} className="stack--section">
            <div className="micro micro-uppercase">{title}</div>
            {children}
        </Stack>
    );
}
