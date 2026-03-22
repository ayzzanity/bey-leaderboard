import { Link } from 'react-router-dom';
import { Table, Tag } from 'antd';

export default function TournamentList({ tournaments, selectedRowKeys, onSelectionChange }) {
  return (
    <Table
      dataSource={tournaments}
      rowKey="id"
      rowSelection={{
        selectedRowKeys,
        onChange: onSelectionChange,
      }}
      columns={[
        { title: 'Tournament', dataIndex: 'name' },
        {
          title: 'Category',
          key: 'category',
          render: (_, row) => (
            <div className="flex gap-2 flex-wrap">
              {row.age_category ? <Tag>{row.age_category === 'open' ? 'Open Cat' : 'Junior Cat'}</Tag> : <Tag>Uncategorized</Tag>}
              {row.event_type ? <Tag color="blue">{row.event_type === 'tournament' ? 'Tournament' : 'Casual'}</Tag> : null}
            </div>
          )
        },
        { title: 'Players', dataIndex: 'participants_count' },
        { title: 'Date', dataIndex: 'date' },
        {
          title: 'Action',
          render: (_, row) => <Link to={`/tournaments/${row.id}`}>View</Link>
        }
      ]}
    />
  );
}
