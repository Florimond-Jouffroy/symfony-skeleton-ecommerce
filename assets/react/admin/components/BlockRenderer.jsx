import React from 'react';

function stripHtml(html) {
    return html.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
}

function ListItem({ item, Tag, listClass }) {
    // @editorjs/list v2 uses objects {content, items[]}, v1 used plain strings
    const text    = typeof item === 'string' ? item : (item.content ?? '');
    const children = typeof item === 'object' ? (item.items ?? []) : [];

    return (
        <li>
            <span dangerouslySetInnerHTML={{ __html: text }} />
            {children.length > 0 && (
                <Tag className={listClass}>
                    {children.map((child, i) => (
                        <ListItem key={i} item={child} Tag={Tag} listClass={listClass} />
                    ))}
                </Tag>
            )}
        </li>
    );
}

function renderBlock(block) {
    switch (block.type) {
        case 'paragraph':
            return (
                <p
                    className="leading-7 [&:not(:first-child)]:mt-4"
                    dangerouslySetInnerHTML={{ __html: block.data.text ?? '' }}
                />
            );

        case 'header': {
            const level = block.data.level ?? 2;
            const cls = {
                2: 'text-2xl font-bold mt-8 mb-2',
                3: 'text-xl font-semibold mt-6 mb-2',
                4: 'text-lg font-semibold mt-4 mb-1',
            }[level] ?? 'text-xl font-bold mt-6 mb-2';
            const Tag = `h${level}`;

            return <Tag className={cls} dangerouslySetInnerHTML={{ __html: block.data.text ?? '' }} />;
        }

        case 'list': {
            const ordered  = block.data.style === 'ordered';
            const Tag      = ordered ? 'ol' : 'ul';
            const listClass = `pl-6 mt-2 ${ordered ? 'list-decimal' : 'list-disc'}`;

            return (
                <Tag className={`my-4 pl-6 ${ordered ? 'list-decimal' : 'list-disc'} [&>li]:mt-1`}>
                    {(block.data.items ?? []).map((item, i) => (
                        <ListItem key={i} item={item} Tag={Tag} listClass={listClass} />
                    ))}
                </Tag>
            );
        }

        case 'quote':
            return (
                <blockquote className="my-6 border-l-4 border-border pl-6 italic">
                    <p dangerouslySetInnerHTML={{ __html: block.data.text ?? '' }} />
                    {block.data.caption && (
                        <footer
                            className="mt-2 text-sm text-muted-foreground not-italic"
                            dangerouslySetInnerHTML={{ __html: `— ${block.data.caption}` }}
                        />
                    )}
                </blockquote>
            );

        case 'code':
            return (
                <pre className="my-4 overflow-x-auto rounded-lg bg-muted p-4">
                    <code className="font-mono text-sm">{block.data.code}</code>
                </pre>
            );

        case 'delimiter':
            return <hr className="my-8 border-border" />;

        case 'image': {
            const url = block.data.file?.url;
            if (!url) return null;

            const width = block.data.width ? `${block.data.width}%` : '100%';
            const align = block.data.align ?? 'center';
            const imgStyle = {
                width,
                display: 'block',
                marginLeft:  align === 'right'  ? 'auto' : (align === 'center' ? 'auto' : '0'),
                marginRight: align === 'left'   ? 'auto' : (align === 'center' ? 'auto' : '0'),
            };

            return (
                <figure className="my-6">
                    <img
                        src={url}
                        alt={block.data.caption ? stripHtml(block.data.caption) : ''}
                        className="rounded-lg max-w-full"
                        style={imgStyle}
                    />
                    {block.data.caption && (
                        <figcaption
                            className="mt-2 text-sm text-muted-foreground text-center"
                            dangerouslySetInnerHTML={{ __html: block.data.caption }}
                        />
                    )}
                </figure>
            );
        }

        default:
            return null;
    }
}

export default function BlockRenderer({ content, coverImage = null }) {
    const blocks = content?.blocks ?? [];

    if (blocks.length === 0) {
        return <p className="text-muted-foreground italic text-sm">Aucun contenu à afficher.</p>;
    }

    return (
        <div className="text-sm leading-relaxed">
            {coverImage && (
                <figure className="mb-8 -mx-6 -mt-5">
                    <img
                        src={coverImage}
                        alt="Couverture"
                        className="w-full max-h-80 object-cover rounded-t-md"
                    />
                </figure>
            )}
            {blocks.map((block, i) => {
                const rendered = renderBlock(block);
                if (!rendered) return null;

                return <React.Fragment key={block.id ?? i}>{rendered}</React.Fragment>;
            })}
        </div>
    );
}
