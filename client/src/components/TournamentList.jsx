import { Link } from 'react-router-dom';
import { Table } from 'antd';

export default function TournamentList({ tournaments }) {
  return (
    <Table
      dataSource={tournaments}
      columns={[
        { title: 'Tournament', dataIndex: 'name' },
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
