import { Select } from '@/Components/ui';

export default function ListPicker({
    lists = [],
    onSelect,
    placeholder = 'Choose a list…',
    className = '',
}) {
    if (lists.length === 0) {
        return null;
    }

    return (
        <Select
            className={className}
            defaultValue=""
            onChange={(e) => {
                if (e.target.value) {
                    onSelect(Number(e.target.value));
                    e.target.value = '';
                }
            }}
        >
            <option value="">{placeholder}</option>
            {lists.map((list) => (
                <option key={list.id} value={list.id}>
                    {list.name}
                </option>
            ))}
        </Select>
    );
}
