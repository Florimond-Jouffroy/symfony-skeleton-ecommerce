import React from 'react';
import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { DataTablePagination } from '@/components/data-table-pagination';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

/**
 * DataTable générique (pattern shadcn) basé sur TanStack Table.
 *
 * Pagination gérée côté serveur : le parent fournit `pageCount`, l'état
 * `pagination` ({ pageIndex, pageSize }) et `onPaginationChange`, et se charge
 * de re-fetcher les données quand l'état change.
 *
 * @param {object}   props
 * @param {Array}    props.columns            Définitions de colonnes TanStack
 * @param {Array}    props.data               Lignes de la page courante
 * @param {number}   props.pageCount          Nombre total de pages
 * @param {number}   props.totalItems         Nombre total de lignes en base
 * @param {object}   props.pagination         { pageIndex, pageSize }
 * @param {Function} props.onPaginationChange Setter de l'état pagination
 * @param {boolean}  [props.loading]          Affiche des lignes squelettes (premier chargement)
 * @param {boolean}  [props.refreshing]       Rechargement : les lignes restent visibles, en fondu
 * @param {string}   [props.emptyMessage]     Message quand il n'y a aucune ligne
 * @param {Function} [props.onRowClick]       Callback appelé avec la row.original au clic sur une ligne
 */
export function DataTable({
    columns,
    data,
    pageCount,
    totalItems,
    pagination,
    onPaginationChange,
    loading = false,
    refreshing = false,
    emptyMessage = 'Aucun résultat.',
    onRowClick,
}) {
    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        pageCount,
        state: { pagination },
        onPaginationChange,
    });

    return (
        <div className="space-y-4">
            <div className="rounded-lg border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id} className={header.column.columnDef.meta?.headerClassName}>
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(header.column.columnDef.header, header.getContext())}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody className={`transition-opacity duration-200 ${refreshing ? 'opacity-50' : 'opacity-100'}`}>
                        {loading ? (
                            Array.from({ length: 5 }).map((_, i) => (
                                <TableRow key={i}>
                                    {columns.map((_, j) => (
                                        <TableCell key={j}><Skeleton className="h-5 w-full" /></TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : table.getRowModel().rows.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="h-24 text-center text-muted-foreground">
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        ) : (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    onClick={onRowClick ? () => onRowClick(row.original) : undefined}
                                    className={onRowClick ? 'cursor-pointer' : undefined}
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id} className={cell.column.columnDef.meta?.cellClassName}>
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            <DataTablePagination table={table} totalItems={totalItems} />
        </div>
    );
}
