import React from 'react'
import { Copy, MoreHorizontal, Pen, Star, Tags, Trash } from "lucide-react"

import { Button } from "../index"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from "../dropdown-menu.jsx"

import { labels } from "../../../data/data"
import { taskSchema } from "../../../data/schema"

export function DataTableRowActions({
  row,
}) {
  const task = taskSchema.parse(row.original)

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          className={"flex h-8 w-8 p-0 data-[state=open]:bg-muted".classNames()}
        >
          <MoreHorizontal className={"h-4 w-4".classNames()} />
          <span className={"sr-only".classNames()}>Open menu</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className={"w-[160px]".classNames()}>
        <DropdownMenuItem>
          <Pen className={"mr-2 h-3.5 w-3.5 text-muted-foreground/70".classNames()} />
          Edit
        </DropdownMenuItem>
        <DropdownMenuItem>
          <Copy className={"mr-2 h-3.5 w-3.5 text-muted-foreground/70".classNames()} />
          Make a copy
        </DropdownMenuItem>
        <DropdownMenuItem>
          <Star className={"mr-2 h-3.5 w-3.5 text-muted-foreground/70".classNames()} />
          Favorite
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuSub>
          <DropdownMenuSubTrigger>
            <Tags className={"mr-2 h-3.5 w-3.5 text-muted-foreground/70".classNames()} />
            Labels
          </DropdownMenuSubTrigger>
          <DropdownMenuSubContent>
            <DropdownMenuRadioGroup value={task.label}>
              {labels.map((label) => (
                <DropdownMenuRadioItem key={label.value} value={label.value}>
                  {label.label}
                </DropdownMenuRadioItem>
              ))}
            </DropdownMenuRadioGroup>
          </DropdownMenuSubContent>
        </DropdownMenuSub>
        <DropdownMenuSeparator />
        <DropdownMenuItem>
          <Trash className={"mr-2 h-3.5 w-3.5 text-muted-foreground/70".classNames()} />
          Delete
          <DropdownMenuShortcut>⌘⌫</DropdownMenuShortcut>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}